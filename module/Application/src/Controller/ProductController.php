<?php

declare(strict_types= 1);

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Application\Model\ProductTable;

class ProductController extends AbstractActionController
{


    private ProductTable $productTable;

    public function __construct(ProductTable $productTable)
    {
        $this->productTable = $productTable;
    }

    // Page de Produits GENERAL
    public function indexAction()
    {
        $products = $this->productTable->fetchAll();
        
        return new ViewModel(
            [
                'pageTitle' => 'Page de Produits',
                'content' => 'Bienvenue sur la page des produits de l\'application Apoteka.',
                'products' => $products
            ]
        );
    }


    // Page de Détails d'un Produit par ID
    public function viewAction()
    {
        $id = (int) $this->params()->fromRoute('id', 0);

        if ($id === 0) {
            return $this->redirect()->toRoute('product');
        }

        try {
            $product = $this->productTable->getProduct($id);
        } catch (\Exception $e) {
            return $this->redirect()->toRoute('product');
        }

        return new ViewModel(
            [
                'pageTitle' => 'Détails du Produit',
                'product' => $product
            ]
        );
    }



}