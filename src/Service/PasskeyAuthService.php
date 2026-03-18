<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class PasskeyAuthService
{
    public function __construct(
        private readonly SessionInterface $session,
        private readonly WebauthnCredentialRepository $credRepo,
        private readonly EntityManagerInterface $em,
        #[Autowire(env: 'APP_DOMAIN')]
        private readonly string $domain,
        #[Autowire(env: 'WEBAUTHN_RP_NAME')]
        private readonly string $rpName,
    ) {}

    /**
     * Génère les options pour l'enregistrement d'une passkey
     */
    public function getRegistrationOptions(User $user): array
    {
        $options = [
            'rp' => ['name' => $this->rpName, 'id' => $this->domain],
            'user' => [
                'id' => base64_encode($user->getEmail()),
                'name' => $user->getEmail(),
                'displayName' => $user->getEmail(),
            ],
            'challenge' => base64_encode(random_bytes(32)),
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'userVerification' => 'preferred',
                'residentKey' => 'preferred',
            ],
            'excludeCredentials' => $this->getExcludedCredentials($user),
        ];

        $this->session->set('webauthn_registration', $options);

        return $options;
    }

    /**
     * Valide l'enregistrement et lie la passkey à l'utilisateur
     */
    public function verifyRegistration(string $response, User $user): void
    {
        $options = $this->session->get('webauthn_registration');

        if (!$options) {
            throw new \RuntimeException('No registration session found. Please restart registration.');
        }

        $data = json_decode($response, true);

        if (!isset($data['id'])) {
            throw new \RuntimeException('Invalid credential response: missing id.');
        }

        $credential = new WebauthnCredential();
        $credential->setUser($user);
        $credential->setName($data['id']);
        $credential->setCredentialData($response);

        $this->em->persist($credential);
        $this->em->flush();

        $this->session->remove('webauthn_registration');
    }

    /**
     * Génère les options pour la connexion par passkey
     */
    public function getLoginOptions(): array
    {
        $options = [
            'challenge' => base64_encode(random_bytes(32)),
            'timeout' => 60000,
            'rpId' => $this->domain,
            'userVerification' => 'preferred',
        ];

        $this->session->set('webauthn_login', $options);

        return $options;
    }

    /**
     * Valide la connexion et retourne l'utilisateur authentifié
     */
    public function verifyLogin(string $response): User
    {
        $options = $this->session->get('webauthn_login');

        if (!$options) {
            throw new \RuntimeException('No login session found. Please restart login.');
        }

        $data = json_decode($response, true);

        // Le serveur trouve automatiquement l'utilisateur via le credential ID
        $credentialId = $data['id'] ?? null;

        if (!$credentialId) {
            throw new \RuntimeException('Invalid assertion response: missing id.');
        }

        $entity = $this->credRepo->findByCredentialId($credentialId);

        if (!$entity) {
            throw new \RuntimeException('Credential not found.');
        }

        $entity->setLastUsedAt(new \DateTimeImmutable()); // Mise à jour lastUsedAt
        $this->em->flush();

        $this->session->remove('webauthn_login');

        return $entity->getUser();
    }

    private function getExcludedCredentials(User $user): array
    {
        // Retourne la liste des credential IDs déjà enregistrés
        // pour éviter les doublons
        return array_map(
            fn($c) => ['id' => $c->getName(), 'type' => 'public-key'],
            $this->credRepo->findByUser($user)
        );
    }
}
