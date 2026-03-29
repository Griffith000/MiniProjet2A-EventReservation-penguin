<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Webauthn\PublicKeyCredentialSource;

/**
 * @extends ServiceEntityRepository<WebauthnCredential>
 */
class WebauthnCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebauthnCredential::class);
    }

    /** @return WebauthnCredential[] */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    public function findByCredentialId(string $credentialId): ?WebauthnCredential
    {
        foreach ($this->findAll() as $credential) {
            $source = $credential->getCredentialSource();
            if ($source->publicKeyCredentialId === $credentialId) {
                return $credential;
            }
        }
        return null;
    }

    public function saveCredential(User $user, PublicKeyCredentialSource $source): void
    {
        $credential = new WebauthnCredential();
        $credential->setUser($user);
        $credential->setCredentialSource($source);
        $credential->setName('Passkey ' . date('Y-m-d H:i'));

        $em = $this->getEntityManager();
        $em->persist($user);
        $em->persist($credential);
        $em->flush();
    }

    /** @return PublicKeyCredentialSource[] */
    public function getCredentialSourcesForUser(User $user): array
    {
        $credentials = $this->findByUser($user);
        return array_map(fn($c) => $c->getCredentialSource(), $credentials);
    }
}
