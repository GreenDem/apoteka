<?php

declare(strict_types=1);

namespace Application;

use Laminas\Mvc\MvcEvent;
use Laminas\Session\SessionManager;

class Module
{
    public function getConfig(): array
    {
        /** @var array $config */
        $config = include __DIR__ . '/../config/module.config.php';
        return $config;
    }

    public function onBootstrap(MvcEvent $event): void
    {
        $serviceManager = $event->getApplication()->getServiceManager();
        /** @var SessionManager $sessionManager */
        $sessionManager = $serviceManager->get(SessionManager::class);

        if (! $sessionManager->sessionExists()) {
            $sessionManager->start();
        }
    }
}
