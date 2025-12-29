#!/usr/bin/env php
<?php

/**
 * Point d'entrée principal de l'application CLI DHL.
 * Configure et lance la commande de génération d'étiquettes DHL.
 *
 * Utilisation :
 *   php dhl.php --account=default --input=shipment.json
 *   php dhl.php --help
 */

use App\Config\AppConfig;
use App\Console\GenerateLabelCommand;
use App\Service\ShipmentServiceDHL;
use Laminas\Cli\ContainerCommandLoader;
use Laminas\ServiceManager\ServiceManager;
use Symfony\Component\Console\Application;

// Charger l'autoloader Composer
require __DIR__ . '/vendor/autoload.php';

// Charger la configuration des comptes DHL
$configPath = __DIR__ . '/config/accounts.php';
$appConfig = new AppConfig($configPath);

// Déterminer le mode HTTP : 'real' (vérification SSL) ou autre (pas de vérification)
// Peut être surchargé via la variable d'environnement DHL_MODE
$mode = getenv('DHL_MODE') ?: 'real';

// Créer le client HTTP Guzzle configuré pour DHL
$httpClient = \App\Factory\DhlClientFactory::create($mode);

// Instancier le service de création d'expéditions
$shipmentService = new ShipmentServiceDHL($appConfig, $httpClient);

// Créer un container simple pour Laminas CLI (injection de dépendances)
$container = new ServiceManager([
    'factories' => [
        GenerateLabelCommand::class => function () use ($shipmentService) {
            return new GenerateLabelCommand($shipmentService);
        },
    ],
]);

// Créer l'application Symfony Console (utilisée par Laminas CLI)
$application = new Application('DHL Label CLI', '0.1.0');

// Configurer le chargeur de commandes depuis le container
$commandLoader = new ContainerCommandLoader($container, [
    'shipment:create' => GenerateLabelCommand::class,
]);
$application->setCommandLoader($commandLoader);

// Définir la commande par défaut (exécutée si aucune commande n'est spécifiée)
$application->setDefaultCommand('shipment:create', true);

// Lancer l'application
$application->run();
