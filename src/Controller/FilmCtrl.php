<?php
namespace App\Controller;

use App\Entity\Film;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\FilmAPI;
use SimpleXLSX;
use Symfony\Component\Validator\Constraints\File;
use CMEN\GoogleChartsBundle\GoogleCharts\Charts\PieChart;

class FilmCtrl extends AbstractController
{
    /**
     * @Route("/add-film", name="add-film")
     */
    public function new(Request $request, HttpClientInterface $httpClient, ManagerRegistry $doctrine): Response
    {

        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
            ->add('name', TextType::class, ['label' => 'Nom du film'])
            ->add('note', NumberType::class)
            ->add('numberOfVoters', NumberType::class, ['label' => 'Nombre de votant' ])
            ->add('email', EmailType::class)
            ->add('file', FileType::class, [
                'label' => 'Image du film',
                'required' => false,
                'constraints' => [
                  new File([ 
                    'mimeTypes' => [
                      'image/png', 
                      'image/jpeg', 
                    ],
                    'mimeTypesMessage' => "Seul les images sont acceptés.",
                  ])
                ],
              ])
            ->add('create', SubmitType::class, ['label' => 'Créer'])
            ->getForm();
            

        $form->handleRequest($request);

        $data = $form->getData();

        $state = 0;
        $error = "";

        if ($form->isSubmitted() && $form->isValid()) {
            // on récupère les données du formulaire
            $data = $form->getData();
            $name = $data["name"];
            $note = (int)$data["note"];
            $numberOfVoters = (int)$data["numberOfVoters"];

            if ($note < 0 or $note > 10) {
                $state = 2;
                $error = "La note doit être comprise entre 0 et 10.";
            }


            $find_films = $doctrine->getRepository(Film::class)->findBy(array('name' => $name));
            if (count($find_films) > 0) {
                $state = 2;
                $error = "Un film avec ce nom existe déjà.";
            }
            
            // on récupère une description
            $filmAPI = new FilmAPI($httpClient);
            $plot = $filmAPI->getDescription($name);

            if ($plot == "") {
                // si le service n'a pas trouvé de description, on affiche une erreur
                $error = "Nous n'avons pas pu trouver de description pour le nom de votre film";
                $state = 2;
            }

            if ($state == 0) {
                $state = 1;
                // on créé l'objet film
                $film = new Film();
                $film->setName($name);
                $film->setDescription($plot);
                $film->setNote($note);
                $film->setNumberOfVoters($numberOfVoters);

                // si l'utilsateur a upload une image, on ajoute son chemin au film
                $file = $data["file"];
                if ($file) {
                    $fileName = md5(uniqid()).'.'.$file->guessExtension(); 
                    $file->move('uploads/image', $fileName);
                    $film->setImage('uploads/image/'.$fileName);
                }

                // on insère le film dans la bdd
                $em = $doctrine->getManager();
                $em->persist($film);
                $em->flush();
            }
        }

        return $this->render('film/add-film.html.twig', [
            'form' => $form->createView(),
            'state' => $state,
            'error' => $error
        ]);
    }

    /**
     * @Route("/view-film/{id}", name="view-film")
     */
    public function view(Request $request, ManagerRegistry $doctrine, int $id): Response
    {
        $film = $doctrine->getRepository(Film::class)->find($id);
        $state = 0;
        
        
        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
            ->add('password', TextType::class)
            ->add('supprimer', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $pwd = $this->getParameter('admin_password');
            
            $data = $form->getData();

            if ($data["password"] != $pwd) {
                $state = 1;
            } else {
                $em = $doctrine->getManager();
                $em->remove($film);
                $em->flush();
                return $this->redirectToRoute('home');
            }
        }

        return $this->render('film/view-film.html.twig', [
            'form' => $form->createView(),
            'film' => $film,
            'state' => $state
        ]);
    }

    /**
     * @Route("/import", name="import")
     */
    public function import(Request $request, ManagerRegistry $doctrine): Response
    {
        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('file', FileType::class, [
            'label' => 'Fichier (.xlsx)',
            'constraints' => [
              new File([ 
                'mimeTypes' => [
                  'application/vnd.ms-excel', 
                  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 
                  'application/xlsx', 
                  'application/excel', 
                  'application/vnd.msexcel', 
                ],
                'mimeTypesMessage' => "Seul les fichiers aux format .xlsx sont acceptés.",
              ])
            ],
          ])
            ->add('envoyer', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) { 
            $data = $form->getData();
            $file = $data["file"];
            $fileName = md5(uniqid()).'.'.$file->guessExtension(); 
            $file->move('uploads/excel', $fileName);
            
            if ($xlsx = SimpleXLSX::parse('uploads/excel/'.$fileName) ) {
                $rows = $xlsx->rows();
                foreach($rows as $item) {
                    $film = new Film();
                    $film->setName($item[0]);
                    $film->setDescription($item[1]);
                    $film->setNote($item[2]);
                    
                    $nb = 0;
                    if (isset($item[3])) { $nb = $item[3]; }
                    $film->setNumberOfVoters($nb);

                    $em = $doctrine->getManager();
                    $em->persist($film);
                    $em->flush();
                }

                return $this->render('film/import_success.html.twig', [
                ]);
            } else {
                echo SimpleXLSX::parseError();
            }
        }
        return $this->render('film/import.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/stats", name="stats-film")
     */
    public function stats(Request $request, ManagerRegistry $doctrine): Response
    {
        $films = $doctrine->getRepository(Film::class)->findAll();
        $array = [
            ['Nom du film', 'Vote du film']
        ];
        foreach($films as $film) {
            $array[] = [$film->getName(), $film->getNote()];
        }

        $pieChart = new PieChart();
        $pieChart->getData()->setArrayToDataTable(
            $array
        );
        $pieChart->getOptions()->setTitle('Représentation des différentes notes des films');
        $pieChart->getOptions()->setHeight(500);
        $pieChart->getOptions()->setWidth(900);
        $pieChart->getOptions()->getTitleTextStyle()->setBold(true);
        $pieChart->getOptions()->getTitleTextStyle()->setColor('#009900');
        $pieChart->getOptions()->getTitleTextStyle()->setItalic(true);
        $pieChart->getOptions()->getTitleTextStyle()->setFontName('Arial');
        $pieChart->getOptions()->getTitleTextStyle()->setFontSize(20);
    
        return $this->render('film/stats.html.twig', array('piechart' => $pieChart));
    }
}

