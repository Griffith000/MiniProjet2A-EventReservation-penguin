<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/login', name: 'app_admin_login', methods: ['GET', 'POST'])]
    public function login(): Response
    {
        return $this->redirectToRoute('app_login');
    }

    #[Route('/logout', name: 'app_admin_logout', methods: ['GET'])]
    public function logout(): void
    {
        // Handled by Symfony security firewall
    }

    #[Route('', name: 'app_admin_dashboard', methods: ['GET', 'POST'])]
    public function dashboard(EventRepository $eventRepo, ReservationRepository $reservationRepo): Response
    {
        $events = $eventRepo->findAll();
        $totalReservations = 0;
        foreach ($events as $event) {
            $totalReservations += $reservationRepo->countByEvent($event->getId());
        }

        return $this->render('admin/dashboard.html.twig', [
            'events' => $events,
            'totalReservations' => $totalReservations,
        ]);
    }

    #[Route('/events/create', name: 'app_admin_event_create', methods: ['GET', 'POST'])]
    public function createEvent(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_event', $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_admin_event_create');
            }

            $event = new Event();
            $this->hydrateEventFromRequest($event, $request);
            $em->persist($event);
            $em->flush();

            $this->addFlash('success', 'Événement créé avec succès.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('admin/event/create.html.twig');
    }

    #[Route('/events/{id}/edit', name: 'app_admin_event_edit', methods: ['GET', 'POST'])]
    public function editEvent(string $id, Request $request, EventRepository $eventRepo, EntityManagerInterface $em): Response
    {
        $event = $eventRepo->find($id);

        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_event_' . $id, $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_admin_event_edit', ['id' => $id]);
            }

            $this->hydrateEventFromRequest($event, $request);
            $em->flush();

            $this->addFlash('success', 'Événement modifié avec succès.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('admin/event/edit.html.twig', ['event' => $event]);
    }

    #[Route('/events/{id}/delete', name: 'app_admin_event_delete', methods: ['POST'])]
    public function deleteEvent(string $id, Request $request, EventRepository $eventRepo, EntityManagerInterface $em): Response
    {
        $event = $eventRepo->find($id);

        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        if (!$this->isCsrfTokenValid('delete_event_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $em->remove($event);
        $em->flush();

        $this->addFlash('success', 'Événement supprimé.');
        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/events/{id}/reservations', name: 'app_admin_reservations', methods: ['GET'])]
    public function reservations(string $id, EventRepository $eventRepo): Response
    {
        $event = $eventRepo->find($id);

        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        return $this->render('admin/reservation/list.html.twig', [
            'event' => $event,
            'reservations' => $event->getReservations(),
        ]);
    }

    private function hydrateEventFromRequest(Event $event, Request $request): void
    {
        $event->setTitle(trim($request->request->getString('title')));
        $event->setDescription(trim($request->request->getString('description')));
        $event->setDate(new \DateTime($request->request->getString('date')));
        $event->setLocation(trim($request->request->getString('location')));
        $event->setSeats((int) $request->request->getString('seats'));
        $imageUrl = trim($request->request->getString('image'));
        $event->setImage($imageUrl !== '' ? $imageUrl : null);
    }
}
