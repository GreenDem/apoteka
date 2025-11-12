<?php

declare(strict_types= 1);

namespace Application\Model;

class Product
{


    // Declaration des propriétés du produit
    protected ?int $id = null;
    protected ?string $name = null;
    protected ?float $price = null;
    protected ?string $description = null;


    // Méthode pour hydrater l'objet avec des données + isset pour gerer le cas des valeurs nulles
    public function exchangeArray(array $data): void
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->name = isset($data['name']) ? $data['name'] : null;
        $this->price = isset($data['price']) ? (float)$data['price'] : null;
        $this->description = isset($data['description']) ? $data['description'] : null;
    }


    //Getters
    public function getId(): int {return $this->id;  }
    public function getName(): ?string {return $this->name; }
    public function getPrice(): float{return $this->price; }
    public function getDescription(): ?string {return $this->description; }
}