<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Form\UserForm;
use Application\Model\User;
use Application\Model\UserTable;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Http\Request;
use Laminas\Http\Response;
use RuntimeException;

final class UserController extends AbstractActionController
{
    public function __construct(
        private readonly UserTable $userTable,
        private readonly UserForm $createForm,
        private readonly UserForm $editForm
    ) {
    }

    // Liste paginée
    public function indexAction(): ViewModel
    {
        

        $page = max(1, (int) $this->params()->fromQuery('page', 1));
        $pageSize = max(1, min(50, (int) $this->params()->fromQuery('limit', 25)));

        $users = $this->userTable->fetchPage($page, $pageSize);

        return new ViewModel([
            'users' => $users,
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    // Détails utilisateur
    public function viewAction(): ViewModel
    {
        

        $id = (int) $this->params()->fromRoute('id');
        $user = $this->userTable->getUser($id);

        return new ViewModel(['user' => $user]);
    }

    // Création
    public function addAction(): ViewModel
    {
        

        /** @var Request $request */
        $request = $this->getRequest();
        $form = clone $this->createForm;

        if (!$request->isPost()) {
            return new ViewModel(['form' => $form]);
        }

        $form->setData($request->getPost());
        if (!$form->isValid()) {
            return new ViewModel(['form' => $form]);
        }

        $data = $form->getData();

        // Vérifie unicité email (NoRecordExists est déprécié)
        if ($this->userTable->fetchByEmail($data['email'])) {
            $form->get('email')->setMessages(['Cet email est déjà utilisé.']);
            return new ViewModel(['form' => $form]);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPasswordHash(password_hash($data['password'], PASSWORD_ARGON2ID));
        $user->setRoles([$data['roles']]);

        $this->userTable->saveUser($user);
        $this->flashMessenger()->addSuccessMessage('Utilisateur créé avec succès.');
        return new ViewModel(['form' => $form]);
    }

    // Édition
    public function editAction(): ViewModel
    {
        

        $id = (int) $this->params()->fromRoute('id');
        $user = $this->userTable->getUser($id);

        $form = clone $this->editForm;
        $form->setData([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles()[0] ?? 'user',
        ]);

        /** @var Request $request */
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new ViewModel(['form' => $form, 'user' => $user]);
        }

        $postData = $request->getPost()->toArray();
        $form->setData($postData);

        // Si password vide, le rendre optionnel
        if (empty($postData['password'])) {
            $form->getInputFilter()->get('password')->setRequired(false);
            $form->getInputFilter()->get('password_confirm')->setRequired(false);
        }

        if (!$form->isValid()) {
            return new ViewModel(['form' => $form, 'user' => $user]);
        }

        $data = $form->getData();

        // Si l’utilisateur change d’email, vérifier l’unicité
        if ($user->getEmail() !== $data['email'] && $this->userTable->fetchByEmail($data['email'])) {
            $form->get('email')->setMessages(['Cet email est déjà utilisé.']);
            return new ViewModel(['form' => $form, 'user' => $user]);
        }

        $user->setEmail($data['email']);
        $user->setRoles([$data['roles']]);

        if (!empty($data['password'])) {
            $user->setPasswordHash(password_hash($data['password'], PASSWORD_ARGON2ID));
        }

        $this->userTable->saveUser($user);

        $this->flashMessenger()->addSuccessMessage('Utilisateur mis à jour.');
        return new ViewModel(['form' => $form, 'user' => $user]);

    }

    // Suppression soft
    public function deleteAction()
    {
        

        $id = (int) $this->params()->fromRoute('id');
        $reason = $this->params()->fromPost('reason', null);



        /** @var Request $request */
        $request = $this->getRequest();

        if (!$request->isPost()) {
            return new ViewModel(['user' => $this->userTable->getUser($id)]);
        }

        try {
            $currentUser = $this->identity();
            

            $this->userTable->softDelete($id, $reason);

            $this->flashMessenger()->addSuccessMessage('Utilisateur désactivé.');
        } catch (RuntimeException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
        }

        return $this->redirect()->toRoute('user');
    }

}