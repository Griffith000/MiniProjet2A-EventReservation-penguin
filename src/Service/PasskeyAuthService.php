<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use App\Repository\UserRepository;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PasskeyAuthService
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly WebauthnCredentialRepository $credentialRepo,
        private readonly EntityManagerInterface $em,
        #[Autowire(env: 'APP_DOMAIN')]
        private readonly string $domain,
        #[Autowire(env: 'WEBAUTHN_RP_NAME')]
        private readonly string $rpName,
    ) {}

    public function getRegistrationOptions(User $user): array
    {
        $challenge = base64_encode(random_bytes(32));

        return [
            'rp' => ['name' => $this->rpName, 'id' => $this->domain],
            'user' => [
                'id' => base64_encode((string) $user->getId()),
                'name' => $user->getUsername(),
                'displayName' => $user->getUsername(),
            ],
            'challenge' => $challenge,
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'requireResidentKey' => true,
                'residentKey' => 'required',
                'userVerification' => 'required',
            ],
        ];
    }

    public function verifyRegistration(User $user, array $credential, string $name): WebauthnCredential
    {
        $entity = new WebauthnCredential();
        $entity->setUser($user);
        $entity->setName($name);
        $entity->setCredentialData(json_encode($credential));

        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    public function getLoginOptions(): array
    {
        $challenge = base64_encode(random_bytes(32));

        return [
            'challenge' => $challenge,
            'timeout' => 60000,
            'rpId' => $this->domain,
            'userVerification' => 'required',
        ];
    }

    public function verifyLogin(array $assertion): ?User
    {
        $credentialId = $assertion['id'] ?? null;

        if (!$credentialId) {
            return null;
        }

        $credential = $this->credentialRepo->findByCredentialId($credentialId);

        if (!$credential) {
            return null;
        }

        $credential->setLastUsedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $credential->getUser();
    }
}
