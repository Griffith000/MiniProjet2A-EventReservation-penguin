<?php

namespace App\DataFixtures;

use App\Entity\Admin;
use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        // Admin
        $admin = new Admin();
        $admin->setUsername('admin');
        $admin->setPasswordHash($this->hasher->hashPassword($admin, 'admin1234'));
        $manager->persist($admin);

        // Users
        $user = new User();
        $user->setUsername('johndoe');
        $user->setPasswordHash($this->hasher->hashPassword($user, 'user1234'));
        $manager->persist($user);

        // Events
        $events = [
            ['Tech Conference 2026', 'Annual technology conference covering AI, cloud, and security.', '+30 days', 'Tunis, Tunisia', 200, 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&h=500&fit=crop'],
            ['Web Dev Workshop', 'Hands-on workshop on Symfony 7 and modern PHP.', '+15 days', 'Sousse, Tunisia', 50, 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=800&h=500&fit=crop'],
            ['Startup Pitch Night', 'Local startups pitch their ideas to investors.', '+7 days', 'Sfax, Tunisia', 100, 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?w=800&h=500&fit=crop'],
            ['Design Sprint', 'A 2-day design sprint for UX/UI enthusiasts.', '+45 days', 'Monastir, Tunisia', 30, 'https://images.unsplash.com/photo-1586717791821-3f44a563fa4c?w=800&h=500&fit=crop'],
            ['Open Source Day', 'Contributing to open source projects together.', '+60 days', 'Tunis, Tunisia', 80, 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=800&h=500&fit=crop'],
        ];

        $createdEvents = [];
        foreach ($events as [$title, $desc, $dateMod, $location, $seats, $image]) {
            $event = new Event();
            $event->setTitle($title);
            $event->setDescription($desc);
            $event->setDate((new \DateTime())->modify($dateMod));
            $event->setLocation($location);
            $event->setSeats($seats);
            $event->setImage($image);
            $manager->persist($event);
            $createdEvents[] = $event;
        }

        // Sample reservations
        $reservation = new Reservation();
        $reservation->setEvent($createdEvents[0]);
        $reservation->setName('Jane Smith');
        $reservation->setEmail('jane@example.com');
        $reservation->setPhone('+216 55 123 456');
        $manager->persist($reservation);

        $manager->flush();
    }
}
