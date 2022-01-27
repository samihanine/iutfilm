<?php

namespace App\Controller;

use App\Entity\Film;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\Persistence\ManagerRegistry;

class Home extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function main(ManagerRegistry $doctrine): Response
    {
        // on récupère l'ensemble des films
        $films = $doctrine->getRepository(Film::class)->findAll();

        return $this->render('home/home.html.twig', [
            'films' => $films
        ]);
    }
}

