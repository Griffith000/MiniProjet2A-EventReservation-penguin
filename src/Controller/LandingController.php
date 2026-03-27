<?php

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LandingController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(EventRepository $eventRepo): Response
    {
        $featuredEvents = $eventRepo->findUpcoming();

        return $this->render('landing/index.html.twig', [
            'featuredEvents' => array_slice($featuredEvents, 0, 3),
        ]);
    }
}
