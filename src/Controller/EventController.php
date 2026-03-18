<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventController extends AbstractController
{
    #[Route('/events', name: 'app_event_list', methods: ['GET'])]
    public function list(EventRepository $eventRepo): Response
    {
        $events = $eventRepo->findUpcoming();

        return $this->render('event/list.html.twig', [
            'events' => $events,
        ]);
    }

    #[Route('/events/{id}', name: 'app_event_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, EventRepository $eventRepo, ReservationRepository $reservationRepo): Response
    {
        $event = $eventRepo->find($id);

        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        $remainingSeats = $event->getSeats() - $reservationRepo->countByEvent($id);

        return $this->render('event/detail.html.twig', [
            'event' => $event,
            'remainingSeats' => $remainingSeats,
        ]);
    }
}
