<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasskeyAuthService;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthApiController extends AbstractController
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenGeneratorInterface $refreshGenerator,
        private RefreshTokenManagerInterface $refreshManager,
    ) {}

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepo,
        UserPasswordHasherInterface $hasher,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $user = $userRepo->findOneBy(['email' => $email]);

        if (!$user || !$hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtManager->create($user);
        $refreshToken = $this->refreshGenerator->createForUserWithTtl($user, 2592000);
        $this->refreshManager->save($refreshToken);

        return $this->json([
            'token' => $token,
            'refresh_token' => $refreshToken->getRefreshToken(),
        ]);
    }

    #[Route('/refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        // Cette route est gérée automatiquement par GesdinetBundle
        // via /api/token/refresh - voir configuration security.yaml
        return $this->json(
            ['error' => 'Utilisez /api/token/refresh'],
            Response::HTTP_BAD_REQUEST
        );
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/register/options', methods: ['POST'])]
    public function registerOptions(
        Request $request,
        UserRepository $userRepo,
        PasskeyAuthService $passkeyService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['error' => 'Email requis'], Response::HTTP_BAD_REQUEST);
        }

        // Créer ou récupérer l'utilisateur
        $user = $userRepo->findOneBy(['email' => $email])
            ?? (new User())->setEmail($email);

        try {
            $options = $passkeyService->getRegistrationOptions($user);

            return $this->json($options);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/register/verify', methods: ['POST'])]
    public function registerVerify(
        Request $request,
        UserRepository $userRepo,
        PasskeyAuthService $passkeyService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $credential = $data['credential'] ?? null;

        $user = $userRepo->findOneBy(['email' => $email]);

        if (!$user || !$credential) {
            return $this->json(['error' => 'Données invalides'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $passkeyService->verifyRegistration(json_encode($credential), $user);

            // Générer les tokens JWT
            $jwt = $this->jwtManager->create($user);
            $refresh = $this->refreshGenerator->createForUserWithTtl($user, 2592000);
            $this->refreshManager->save($refresh);

            return $this->json([
                'success' => true,
                'token' => $jwt,
                'refresh_token' => $refresh->getRefreshToken(),
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login/options', methods: ['POST'])]
    public function loginOptions(PasskeyAuthService $passkeyService): JsonResponse
    {
        try {
            return $this->json($passkeyService->getLoginOptions());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login/verify', methods: ['POST'])]
    public function loginVerify(
        Request $request,
        PasskeyAuthService $passkeyService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $credential = $data['credential'] ?? null;

        if (!$credential) {
            return $this->json(['error' => 'Credential requis'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $passkeyService->verifyLogin(json_encode($credential));

            $jwt = $this->jwtManager->create($user);
            $refresh = $this->refreshGenerator->createForUserWithTtl($user, 2592000);
            $this->refreshManager->save($refresh);

            return $this->json([
                'success' => true,
                'token' => $jwt,
                'refresh_token' => $refresh->getRefreshToken(),
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
