<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Authentication\Adapter\Argon2DbAdapter;
use Application\Form\LoginForm;
use Application\Form\RegisterForm;
use Application\Model\User;
use Application\Model\UserTable;
use Laminas\Authentication\AuthenticationService;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\SessionManager;
use Laminas\View\Model\ViewModel;
use DateTimeImmutable;

final class AuthController extends AbstractActionController
{
    public function __construct(
        private readonly AuthenticationService $authService,
        private readonly SessionManager $sessionManager,
        private readonly UserTable $userTable,
        private readonly LoginForm $loginForm,
        private readonly RegisterForm $registerForm
    ) {
    }

    public function loginAction(): Response|ViewModel
    {
        if ($this->identity()) {
            return $this->redirect()->toRoute('home');
        }

        /** @var Request $request */
        $request = $this->getRequest();
        $form = clone $this->loginForm;

        if (!$request->isPost()) {
            return new ViewModel(['form' => $form]);
        }

        $form->setData($request->getPost());
        if (!$form->isValid()) {
            return new ViewModel(['form' => $form]);
        }

        $data = $form->getData();
        /** @var Argon2DbAdapter $adapter */
        $adapter = $this->authService->getAdapter();
        $adapter->setIdentity($data['email']);
        $adapter->setCredential($data['password']);

        $result = $this->authService->authenticate();
        if (!$result->isValid()) {
            $form->setMessages([
                'email' => ['Identifiants invalides.'],
            ]);
            return new ViewModel(['form' => $form]);
        }

        /** @var \Application\Model\User $user */
        $user = $result->getIdentity();

        $this->authService->getStorage()->write($user);
        if ($user->getId() !== null) {
            $this->userTable->touchLastLoginAt($user->getId());
        }
        $this->sessionManager->regenerateId(true);
        $this->flashMessenger()->addSuccessMessage('Bienvenue !');

        return $this->redirect()->toRoute('user');
    }

    public function registerAction(): Response|ViewModel
    {
        if ($this->identity()) {
            return $this->redirect()->toRoute('home');
        }

        /** @var Request $request */
        $request = $this->getRequest();
        $form    = clone $this->registerForm;

        if (! $request->isPost()) {
            return new ViewModel(['form' => $form]);
        }

        $form->setData($request->getPost());
        if (! $form->isValid()) {
            return new ViewModel(['form' => $form]);
        }

        $data = $form->getData();

        if ($this->userTable->fetchByEmail($data['email'])) {
            $form->get('email')->setMessages(['Cet email est déjà utilisé.']);
            return new ViewModel(['form' => $form]);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPasswordHash(password_hash($data['password'], PASSWORD_ARGON2ID));
        $user->setRoles(['user']);
        $user->setLastLoginAt((new DateTimeImmutable())->format('Y-m-d H:i:s'));

        $this->userTable->saveUser($user);

        $this->authService->getStorage()->write($user);
        $this->sessionManager->regenerateId(true);

        $this->flashMessenger()->addSuccessMessage('Compte créé avec succès. Vous êtes connecté(e).');

        return $this->redirect()->toRoute('home');
    }

    public function logoutAction()
    {
        $this->authService->clearIdentity();
        $this->sessionManager->destroy();
        $this->flashMessenger()->addSuccessMessage('Vous êtes déconnecté(e).');

        return $this->redirect()->toRoute('auth', ['action' => 'login']);
    }
}