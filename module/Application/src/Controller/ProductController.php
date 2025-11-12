<?php

declare(strict_types= 1);

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class ProductController extends AbstractActionController
{

    // Page de Produits GENERAL
    public function indexAction()
    {
        return new ViewModel(
            [
                'pageTitle' => 'Page de Produits',
                'content' => 'Bienvenue sur la page des produits de l\'application Apoteka.'
            ]
        );
    }


    // Page de Détails d'un Produit par ID
    public function viewAction()
    {
        $id = (int) $this->params()->fromRoute('id', 0);

        return new ViewModel(
            [
                'pageTitle' => 'Détails du Produit',
                'content' => "Vous consultez les détails du produit avec l'ID : $id."
            ]
        );
    }



}