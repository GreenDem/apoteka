<?php

declare(strict_types=1);

namespace Dhl;

use Laminas\Mvc\MvcEvent;

class Module
{
    public function getConfig(): array
    {
        /** @var array $config */
        $config = include __DIR__ . '/config/module.config.php';
        return $config;
    }

    public function onBootstrap(MvcEvent $event): void
    {
        // Initialisation optionnelle si nécessaire
    }
}