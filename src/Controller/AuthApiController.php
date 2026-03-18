<?php

namespace App\Controller;

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
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;

#[Route('/api/auth')]
class AuthApiController extends AbstractController
{
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepo,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $user = $userRepo->findOneBy(['username' => $username]);

        if (!$user || !$hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $jwtManager->create($user);
        $refreshToken = $refreshTokenGenerator->createForUserWithTtl($user, 2592000);
        $refreshTokenManager->save($refreshToken);

        return $this->json([
            'token' => $token,
            'refresh_token' => $refreshToken->getRefreshToken(),
        ]);
    }

    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        // Handled by gesdinet/jwt-refresh-token-bundle at /api/token/refresh
        return $this->json(['error' => 'Use /api/token/refresh'], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Not authenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/register/options', name: 'api_passkey_register_options', methods: ['POST'])]
    public function registerOptions(
        Request $request,
        UserRepository $userRepo,
        PasskeyAuthService $passkeyService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? '';

        $user = $userRepo->findOneBy(['username' => $username]);

        if (!$user) {
            return $this->json(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $options = $passkeyService->getRegistrationOptions($user);

        return $this->json($options);
    }

    #[Route('/register/verify', name: 'api_passkey_register_verify', methods: ['POST'])]
    public function registerVerify(
        Request $request,
        UserRepository $userRepo,
        PasskeyAuthService $passkeyService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? '';
        $credential = $data['credential'] ?? [];
        $name = $data['name'] ?? 'My Passkey';

        $user = $userRepo->findOneBy(['username' => $username]);

        if (!$user) {
            return $this->json(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $passkeyService->verifyRegistration($user, $credential, $name);

        return $this->json(['status' => 'Passkey registered.']);
    }

    #[Route('/login/options', name: 'api_passkey_login_options', methods: ['POST'])]
    public function loginOptions(PasskeyAuthService $passkeyService): JsonResponse
    {
        return $this->json($passkeyService->getLoginOptions());
    }

    #[Route('/login/verify', name: 'api_passkey_login_verify', methods: ['POST'])]
    public function loginVerify(
        Request $request,
        PasskeyAuthService $passkeyService,
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $assertion = $data['assertion'] ?? [];

        $user = $passkeyService->verifyLogin($assertion);

        if (!$user) {
            return $this->json(['error' => 'Passkey verification failed.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $jwtManager->create($user);
        $refreshToken = $refreshTokenGenerator->createForUserWithTtl($user, 2592000);
        $refreshTokenManager->save($refreshToken);

        return $this->json([
            'token' => $token,
            'refresh_token' => $refreshToken->getRefreshToken(),
        ]);
    }
}
