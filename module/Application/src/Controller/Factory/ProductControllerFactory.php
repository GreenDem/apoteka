<?php

declare(strict_types=1);

namespace Application\Controller\Factory;

use Application\Controller\ProductController;
use Application\Form\ProductForm;
use Application\Model\ProductTable;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ProductControllerFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): ProductController {
        return new ProductController(
            $container->get(ProductTable::class),
            $container->get(ProductForm::class)
        );
    }
}