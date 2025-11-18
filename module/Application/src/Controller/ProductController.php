<?php

/**
 * TODO : METTRE EN PLACE LA VERIFICATION DE ROLE ADMIN POUR LE C UD . LE R DOIT RESTER ADMIN ET USER
 */

declare(strict_types=1);

namespace Application\Controller;

use Application\Form\ProductForm;
use Application\Model\Product;
use Application\Model\ProductTable;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use RuntimeException;

final class ProductController extends AbstractActionController
{
    public function __construct(
        private readonly ProductTable $productTable,
        private readonly ProductForm $productForm // Injection de ProductForm pour éviter de le créer dans chaque action
    ) {
    }

    // Page de Produits GENERAL
    public function indexAction(): ViewModel
    {
        return new ViewModel([
            'products' => $this->productTable->fetchAll(),
            'pageTitle' => 'Gestion des produits',
        ]);
    }

    // Détails produit
    public function viewAction(): ViewModel
    {
        $id = (int) $this->params()->fromRoute('id', 0);
        if ($id === 0) {
            return $this->notFoundAction();
        }

        return new ViewModel([
            'product' => $this->productTable->getProduct($id),
        ]);
    }

    // Création produit
    /**
     * Affiche le formulaire d'ajout (GET) ou traite la soumission (POST)
     */
    public function addAction(): Response|ViewModel
    {

        // Clone du formulaire pour éviter les effets de bord entre requêtes
        $form = clone $this->productForm;

        // Retire le champ id pour l'ajout (il sera généré automatiquement par la DB)
        $form->remove('id');

        /** @var Request $request */
        $request = $this->getRequest();

        // Si GET : affiche le formulaire vide
        if (!$request->isPost()) {
            return new ViewModel(['form' => $form]);
        }

        // Si POST : traite la soumission
        $product = new Product();
        $form->bind($product); // Lie le formulaire à l'objet Product

        $form->setData($request->getPost());

        // Validation : si invalide, réaffiche le formulaire avec les erreurs
        if (!$form->isValid()) {
            return new ViewModel(['form' => $form]);
        }

        // Formulaire valide : sauvegarde et redirection
        $this->productTable->saveProduct($product);
        $this->flashMessenger()->addSuccessMessage('Produit créé avec succès.');

        return $this->redirect()->toRoute('product');
    }


    // Modification produit
    public function editAction(): Response|ViewModel
    {

        $id = (int) $this->params()->fromRoute('id', 0);

        if ($id === 0) {
            return $this->notFoundAction();
        }

        // Charge le produit existant
        try {
            $product = $this->productTable->getProduct($id);
        } catch (\RuntimeException $e) {
            $this->flashMessenger()->addErrorMessage('Produit introuvable.');
            return $this->redirect()->toRoute('product');
        }

        $form = clone $this->productForm;
        $form->bind($product); // Lie le formulaire au produit existant

        /** @var Request $request */
        $request = $this->getRequest();

        // Si GET : pré-remplit le formulaire avec les données du produit
        if (!$request->isPost()) {
            return new ViewModel([
                'form' => $form,
                'product' => $product, // Pour afficher des infos supplémentaires dans la vue
            ]);
        }

        // Si POST : traite la mise à jour
        $form->setData($request->getPost());

        if (!$form->isValid()) {
            return new ViewModel(['form' => $form, 'product' => $product]);
        }

        // Mise à jour réussie
        $this->productTable->saveProduct($product);
        $this->flashMessenger()->addSuccessMessage('Produit modifié avec succès.');

        return $this->redirect()->toRoute('product', ['action' => 'view', 'id' => $id]);
    }

    // Suppression produit
    public function deleteAction(): Response|ViewModel
    {

        $id = (int) $this->params()->fromRoute('id', 0);

        if ($id === 0) {
            return $this->notFoundAction();
        }

        try {
            $product = $this->productTable->getProduct($id);
        } catch (RuntimeException $e) {
            $this->flashMessenger()->addErrorMessage('Produit introuvable.');
            return $this->redirect()->toRoute('product');
        }

        // Création d'un formulaire simple pour la suppression avec CSRF
        $form = new \Laminas\Form\Form('delete-form');
        $form->add(new \Laminas\Form\Element\Csrf('csrf'));
        $form->add(new \Laminas\Form\Element\Submit('submit', [
            'value' => 'Confirmer la suppression',
            'attributes' => ['class' => 'btn btn-danger'],
        ]));

        /** @var Request $request */
        $request = $this->getRequest();

        if (!$request->isPost()) {
            return new ViewModel(['product' => $product, 'form' => $form]);
        }

        $form->setData($request->getPost());

        if (!$form->isValid()) {
            $this->flashMessenger()->addErrorMessage('Token CSRF invalide.');
            return new ViewModel(['product' => $product, 'form' => $form]);
        }

        $this->productTable->deleteProduct($id);
        $this->flashMessenger()->addSuccessMessage('Produit supprimé avec succès.');

        return $this->redirect()->toRoute('product');
    }
}