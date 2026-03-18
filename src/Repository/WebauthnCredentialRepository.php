<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
            $data = json_decode($credential->getCredentialData(), true);
            if (($data['id'] ?? null) === $credentialId) {
                return $credential;
            }
        }
        return null;
    }
}
