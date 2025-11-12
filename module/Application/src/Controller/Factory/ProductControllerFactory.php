<?php 


declare(strict_types=1);

namespace Application\Controller\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use Application\Model\ProductTable;
use Application\Controller\ProductController;

class ProductControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ProductController
    {


        $productTable = $container->get(ProductTable::class);
        return new ProductController($productTable);
    }
}
