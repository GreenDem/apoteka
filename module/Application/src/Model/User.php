<?php

declare(strict_types=1);

namespace Application\Model;

class User
{
    protected ?int $id = null;
    protected string $email = '';
    protected string $passwordHash = '';
    protected array $roles = ['user']; //défaut sécurisé : user uniquement
    protected string $createdAt = '';
    protected string $updatedAt = '';
    protected ?string $deletedAt = null;
    protected ?string $lastLoginAt = null;

    //ID getter and setter
    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    //Email getter and setter
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void {
        $this->email = mb_strtolower(trim($email)); //Nettoyage de l'email
    }

    //Password hash getter and setter
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function setPasswordHash(string $hash): void { $this->passwordHash = $hash; }

    //Roles getter and setter
    public function getRoles(): array { return $this->roles; }
    public function setRoles(array $roles): void {
        $allowed = ['user', 'admin']; //Roles autorisés UNIQUEMENT user et admin
        $clean = array_values(array_unique(array_map('strval', $roles)));
        $clean = array_values(array_intersect($clean, $allowed));
        $this->roles = $clean === [] ? ['user'] : $clean;
    }

    //Created at getter and setter
    public function getCreatedAt(): string { return $this->createdAt; }
    public function setCreatedAt(string $createdAt): void { $this->createdAt = $createdAt; }

    //Updated at getter and setter
    public function getUpdatedAt(): string { return $this->updatedAt; }
    public function setUpdatedAt(string $updatedAt): void { $this->updatedAt = $updatedAt; }

    //Deleted at getter and setter
    public function getDeletedAt(): ?string { return $this->deletedAt; }
    public function setDeletedAt(?string $deletedAt): void { $this->deletedAt = $deletedAt; }

    //Last login at getter and setter
    public function getLastLoginAt(): ?string { return $this->lastLoginAt; }
    public function setLastLoginAt(?string $lastLoginAt): void { $this->lastLoginAt = $lastLoginAt; }
}