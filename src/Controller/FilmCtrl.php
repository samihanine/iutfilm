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

class FilmCtrl extends AbstractController
{
    /**
     * @Route("/add-film", name="add-film")
     */
    public function new(Request $request, HttpClientInterface $httpClient, ManagerRegistry $doctrine): Response
    {

        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
            ->add('name', TextType::class)
            ->add('note', NumberType::class)
            ->add('email', EmailType::class)
            ->add('create', SubmitType::class)
            ->getForm();
            

        $form->handleRequest($request);

        $data = $form->getData();

        $state = 0;

        if ($form->isSubmitted() && $form->isValid()) {
            // data is an array with "name", 
            $data = $form->getData();

            $name = $data["name"];
            $note = $data["note"];
            
            $filmAPI = new FilmAPI($httpClient);
            $plot = $filmAPI->getDescription($name);

            if ($plot == "") {
                $state = 2;
            } else {
                $state = 1;
                
                $film = new Film();
                $film->setName($name);
                $film->setDescription($plot);
                $film->setNote($note);
                $film->setNumberOfVoters(0);

                $em = $doctrine->getManager();
                $em->persist($film);
                $em->flush();
            }
        }

        return $this->render('film/add-film.html.twig', [
            'form' => $form->createView(),
            'state' => $state
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
                    $film->setNumberOfVoters($item[3]);

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

}

