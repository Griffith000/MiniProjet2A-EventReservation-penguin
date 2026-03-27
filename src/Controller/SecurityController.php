<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectAfterLogin();
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/login/redirect', name: 'app_login_redirect', methods: ['GET', 'POST'])]
    public function redirectAfterLogin(): Response
    {
        $user = $this->getUser();
        if ($user && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('app_admin_dashboard');
        }
        return $this->redirectToRoute('app_event_list');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        UserRepository $userRepo,
    ): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_event_list');
        }

        if ($request->isMethod('POST')) {
            $email = trim($request->request->getString('email'));
            $password = $request->request->getString('password');

            if (!$email || !$password) {
                $this->addFlash('error', 'Veuillez saisir email et mot de passe.');
                return $this->redirectToRoute('app_register');
            }

            $existing = $userRepo->findOneBy(['email' => $email]);
            if ($existing) {
                $this->addFlash('error', 'Un compte avec cet email existe déjà.');
                return $this->redirectToRoute('app_register');
            }

            $user = new User();
            $user->setEmail($email);
            $user->setPasswordHash($hasher->hashPassword($user, $password));
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Compte créé ! Veuillez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig');
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        // Handled by Symfony security firewall
    }
}
