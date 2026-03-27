<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\User\UserInterface;

class ReservationController extends AbstractController
{
    #[Route('/events/{id}/reserve', name: 'app_reservation_form', methods: ['GET'], requirements: ['id' => '.+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function form(string $id, EventRepository $eventRepo, ReservationRepository $reservationRepo): Response
    {
        $event = $eventRepo->find($id);

        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        $remainingSeats = $event->getSeats() - $reservationRepo->countByEvent($id);

        if ($remainingSeats <= 0) {
            $this->addFlash('error', 'Désolé, cet événement est complet.');
            return $this->redirectToRoute('app_event_detail', ['id' => $id]);
        }

        $user = $this->getUser();

        return $this->render('reservation/form.html.twig', [
            'event' => $event,
            'user' => $user,
        ]);
    }

    #[Route('/events/{id}/reserve', name: 'app_reservation_submit', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function submit(
        string $id,
        Request $request,
        EventRepository $eventRepo,
        ReservationRepository $reservationRepo,
        EntityManagerInterface $em,
        MailerService $mailer
    ): Response {
        $event = $eventRepo->find($id);
        $user = $this->getUser();

        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        if (!$this->isCsrfTokenValid('reservation_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_reservation_form', ['id' => $id]);
        }

        $remainingSeats = $event->getSeats() - $reservationRepo->countByEvent($id);

        if ($remainingSeats <= 0) {
            $this->addFlash('error', 'Désolé, cet événement est complet.');
            return $this->redirectToRoute('app_event_detail', ['id' => $id]);
        }

        $reservation = new Reservation();
        $reservation->setEvent($event);
        $reservation->setUser($user);
        $reservation->setName(trim($request->request->getString('name')));
        $reservation->setEmail(trim($request->request->getString('email')));
        $reservation->setPhone(trim($request->request->getString('phone')));

        $em->persist($reservation);
        $em->flush();

        try {
            $mailer->sendReservationConfirmation($reservation);
        } catch (\Exception $e) {
            // Log error but don't fail the reservation
        }

        return $this->redirectToRoute('app_reservation_confirmation', ['id' => $reservation->getId()]);
    }

    #[Route('/reservations/{id}/confirmation', name: 'app_reservation_confirmation', methods: ['GET'])]
    public function confirmation(string $id, ReservationRepository $reservationRepo): Response
    {
        $reservation = $reservationRepo->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found.');
        }

        return $this->render('reservation/confirmation.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}
