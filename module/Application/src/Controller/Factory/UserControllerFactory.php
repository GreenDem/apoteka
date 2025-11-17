<?php

declare(strict_types=1);

namespace Application\Controller\Factory;

use Application\Controller\UserController;
use Application\Model\UserTable;
use Psr\Container\ContainerInterface;
use Application\Form\UserForm;

final class UserControllerFactory
{
    public function __invoke(ContainerInterface $container): UserController
    {
        $userTable = $container->get(UserTable::class);

        /** @var UserForm $createForm */
        $createForm = $container->get('Form\UserCreate');

        /** @var UserForm $editForm */
        $editForm   = $container->get('Form\UserEdit');

        return new UserController($userTable, $createForm, $editForm);
    }
}