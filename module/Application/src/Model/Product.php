<?php

declare(strict_types=1);

namespace Application\Model;

class Product
{
    // Déclaration des propriétés du produit
    private ?int $id = null;
    private string $name = '';
    private ?string $description = null;
    private float $price = 0.0;
    private int $stock = 0;
    private ?string $img = null;
    private string $createdAt = '';
    private string $updatedAt = '';

    // ID getter and setter
    public function getId(): ?int
    {
        return $this->id;
    }
    public function setId(int|string|null $id): void
    {
        // Validation stricte : accepte uniquement null, int, ou string numérique valide
        if ($id === '' || $id === null) {
            $this->id = null;
            return;
        }

        // Validation : doit être un entier positif ou 0
        if (is_string($id)) {
            // Vérifie que la chaîne est un entier valide (pas de caractères non numériques)
            if (!ctype_digit((string) $id) && $id !== '0') {
                throw new \InvalidArgumentException('L\'ID doit être un entier positif ou null.');
            }
            $id = (int) $id;
        }

        // Validation finale : l'ID doit être positif ou null
        if ($id < 0) {
            throw new \InvalidArgumentException('L\'ID ne peut pas être négatif.');
        }

        $this->id = $id;
    }

    // Name getter and setter
    public function getName(): string
    {
        return $this->name;
    }
    public function setName(string $name): void
    {
        $trimmed = trim($name);
        if (mb_strlen($trimmed) > 100) {
            throw new \InvalidArgumentException('Le nom ne peut pas dépasser 100 caractères.'); // Exception si le nom dépasse 100 caractères
        }
        $this->name = $trimmed;
    }

    // Description getter and setter
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $description): void
    {
        $this->description = $description === null ? null : trim($description); // Nettoyage de la description
    }

    // Price getter and setter
    public function getPrice(): float
    {
        return $this->price;
    }
    public function setPrice(float|int|string $price): void
    {
        $this->price = round((float) $price, 2); // Arrondi du prix à 2 décimales (€)
    }

    // Stock getter and setter
    public function getStock(): int
    {
        return $this->stock;
    }
    public function setStock(int|string $stock): void
    {
        $this->stock = max(0, (int) $stock);
    }

    // Img getter and setter
    public function getImg(): ?string
    {
        return $this->img;
    }
    public function setImg(?string $img): void
    {
        $this->img = $img ? trim($img) : 'https://upload.wikimedia.org/wikipedia/commons/6/65/No-Image-Placeholder.svg'; // Image par défaut si aucune image n'est fournie
    }

    // Created at getter and setter
    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
    public function setCreatedAt(string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    // Updated at getter and setter
    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }
    public function setUpdatedAt(string $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}