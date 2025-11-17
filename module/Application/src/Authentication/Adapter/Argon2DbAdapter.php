<?php

declare(strict_types=1);

namespace Application\Authentication\Adapter;

use Application\Model\User;
use Application\Model\UserTable;
use Laminas\Authentication\Adapter\AdapterInterface;
use Laminas\Authentication\Result;

final class Argon2DbAdapter implements AdapterInterface
{
    private string $identity = '';
    private string $credential = '';

    public function __construct(
        private readonly UserTable $userTable
    ) {
    }

    public function setIdentity(string $identity): self
    {
        $this->identity = $identity;
        return $this;
    }

    public function setCredential(string $credential): self
    {
        $this->credential = $credential;
        return $this;
    }

    public function authenticate(): Result
    {
        $identity = mb_strtolower(trim($this->identity));
        $credential = $this->credential;

        if ($identity === '' || $credential === '') {
            return new Result(Result::FAILURE_CREDENTIAL_INVALID, null, ['Identifiants manquants.']);
        }

        $user = $this->userTable->fetchByEmail($identity);
        if (! $user instanceof User) {
            return new Result(Result::FAILURE_IDENTITY_NOT_FOUND, null, ['Utilisateur introuvable.']);
        }

        if (! password_verify($credential, $user->getPasswordHash())) {
            return new Result(Result::FAILURE_CREDENTIAL_INVALID, null, ['Identifiants invalides.']);
        }

        return new Result(Result::SUCCESS, $user, ['Authentifié.']);
    }
}

