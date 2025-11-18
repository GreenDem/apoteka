<?php
/**
 * TODO : METTRE EN PLACE LA VERIFICATION DE ROLE ADMIN POUR LE C UD . LE R DOIT RESTER ADMIN ET USER
 */

declare(strict_types=1);

namespace Application\Model;

use DateTimeImmutable;
use Laminas\Db\TableGateway\TableGatewayInterface;
use Laminas\Db\Sql\Select;
use RuntimeException;

final class ProductTable
{
    public function __construct(private TableGatewayInterface $tableGateway) {}


    // Récupère toutes les produits
    public function fetchAll(): array
    {
        $resultSet = $this->tableGateway->select(function (Select $select) {
            $select->order('created_at DESC'); // Tri par date de création décroissante
        });

        return iterator_to_array($resultSet, false); // Conversion en tableau associatif
    }

    // Récupère un produit par son ID
    public function getProduct(int $id): Product
    {
        $rowset = $this->tableGateway->select(['id' => $id]);
        $product = $rowset->current(); // Récupération du produit

        if (! $product instanceof Product) {
            throw new RuntimeException("Produit $id introuvable.");
        }

        return $product;
    }

    // Enregistre ou met à jour un produit
    public function saveProduct(Product $product): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $data = [
            'name'        => $product->getName(),
            'description' => $product->getDescription(),
            'price'       => $product->getPrice(),
            'stock'       => $product->getStock(),
            'img'         => $product->getImg(),
            'updated_at'  => $now,
        ];

        if ($product->getId() === null) {
            $data['created_at'] = $now;
            $this->tableGateway->insert($data);
            $product->setId((int) $this->tableGateway->getLastInsertValue());
            return;
        }

        $this->getProduct($product->getId()); // vérifie existence
        $this->tableGateway->update($data, ['id' => $product->getId()]);
    }

    // Supprime un produit par son ID
    public function deleteProduct(int $id): void
    {
        $this->tableGateway->delete(['id' => $id]);
    }
}