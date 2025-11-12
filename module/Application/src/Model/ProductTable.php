<?php


declare(strict_types=1);

namespace Application\Model;

use Laminas\Db\TableGateway\TableGatewayInterface;
use RuntimeException;


class ProductTable
{
    private TableGatewayInterface $tableGateway;

    public function __construct(TableGatewayInterface $tableGateway)
    {
        $this->tableGateway = $tableGateway;
    }

    // Récupérer tous les produits dans un tableau
    public function fetchAll(): array
    {
        $resultSet = $this->tableGateway->select();
        $array = [];
        foreach ($resultSet as $row) {
            $array[] = $row;
        }
        return $array;
    }

    // Récupérer un produit par son ID
    public function getProduct(int $id): Product
    {
        $rowset = $this->tableGateway->select(['id' => $id]);
        foreach ($rowset as $row) {
            return $row;
        }
        throw new RuntimeException(sprintf(
            'Le produit avec l\'id %d n\'a pas été trouvé.',
            $id
        ));
    }
}