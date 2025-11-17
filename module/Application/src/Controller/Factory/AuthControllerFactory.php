<?php

declare(strict_types=1);

namespace Application\Controller\Factory;

use Application\Controller\AuthController;
use Application\Form\LoginForm;
use Application\Form\RegisterForm;
use Application\Model\UserTable;
use Laminas\Authentication\AuthenticationService;
use Laminas\Session\SessionManager;
use Psr\Container\ContainerInterface;

final class AuthControllerFactory
{
    public function __invoke(ContainerInterface $container): AuthController
    {
        return new AuthController(
            $container->get(AuthenticationService::class),
            $container->get(SessionManager::class),
            $container->get(UserTable::class),
            $container->get(LoginForm::class),
            $container->get(RegisterForm::class)
        );
    }
}