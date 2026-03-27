<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyAuthService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly WebauthnCredentialRepository $credRepo,
        private readonly EntityManagerInterface $em,
        private readonly PublicKeyCredentialLoader $credentialLoader,
        private readonly AuthenticatorAttestationResponseValidator $attestationValidator,
        private readonly AuthenticatorAssertionResponseValidator $assertionValidator,
        private readonly string $domain,
        private readonly string $rpName,
    ) {}

    /**
     * Génère les options pour l'enregistrement d'une passkey
     */
    public function getRegistrationOptions(User $user): array
    {
        $userEntity = new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            $user->getId()->toBinary(),
            $user->getEmail()
        );

        $rpEntity = new PublicKeyCredentialRpEntity($this->rpName, $this->domain);

        $challenge = random_bytes(32);

        $pubKeyCredParams = [
            PublicKeyCredentialParameters::create('public-key', -7),
            PublicKeyCredentialParameters::create('public-key', -257),
        ];

        $authenticatorSelection = AuthenticatorSelectionCriteria::create(
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
        );

        $options = PublicKeyCredentialCreationOptions::create(
            rp: $rpEntity,
            user: $userEntity,
            challenge: $challenge,
            pubKeyCredParams: $pubKeyCredParams,
            authenticatorSelection: $authenticatorSelection,
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $this->getExcludedCredentials($user),
            timeout: 60000,
        );

        $this->requestStack->getSession()->set('webauthn_registration', $options);

        return $options->jsonSerialize();
    }

    /**
     * Valide l'enregistrement et lie la passkey à l'utilisateur
     */
    public function verifyRegistration(string $response, User $user): void
    {
        $options = $this->requestStack->getSession()->get('webauthn_registration');

        if (!$options) {
            throw new \RuntimeException('No registration session found.');
        }

        $userEntity = new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            $user->getId()->toBinary(),
            $user->getEmail()
        );

        $publicKeyCredential = $this->credentialLoader->load($response);
        $authenticatorResponse = $publicKeyCredential->response;

        if (!$authenticatorResponse instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Invalid response type.');
        }

        $credentialSource = $this->attestationValidator->check(
            $authenticatorResponse,
            $options,
            $this->domain,
        );

        // Sauvegarde en base via le repository
        $this->credRepo->saveCredential($user, $credentialSource);
        $this->requestStack->getSession()->remove('webauthn_registration');
    }

    /**
     * Génère les options pour la connexion par passkey
     */
    public function getLoginOptions(): array
    {
        $challenge = random_bytes(32);

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->domain,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: 60000,
        );

        $this->requestStack->getSession()->set('webauthn_login', $options);

        return $options->jsonSerialize();
    }

    /**
     * Valide la connexion et retourne l'utilisateur authentifié
     */
    public function verifyLogin(string $response): User
    {
        $options = $this->requestStack->getSession()->get('webauthn_login');

        if (!$options) {
            throw new \RuntimeException('No login session found.');
        }

        $publicKeyCredential = $this->credentialLoader->load($response);
        $authenticatorResponse = $publicKeyCredential->response;

        if (!$authenticatorResponse instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Invalid response type.');
        }

        // Le serveur trouve automatiquement l'utilisateur via le credential ID
        $entity = $this->credRepo->findByCredentialId(
            $publicKeyCredential->rawId
        );

        if (!$entity) {
            throw new \RuntimeException('Credential not found.');
        }

        $credentialSource = $entity->getCredentialSource();

        $this->assertionValidator->check(
            $credentialSource,
            $authenticatorResponse,
            $options,
            $this->domain,
            $credentialSource->userHandle,
        );

        // Mise à jour lastUsedAt
        $entity->setLastUsedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->requestStack->getSession()->remove('webauthn_login');

        return $entity->getUser();
    }

    private function getExcludedCredentials(User $user): array
    {
        $sources = $this->credRepo->getCredentialSourcesForUser($user);
        return array_map(
            fn(PublicKeyCredentialSource $source) => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $source->publicKeyCredentialId,
            ),
            $sources
        );
    }
}
