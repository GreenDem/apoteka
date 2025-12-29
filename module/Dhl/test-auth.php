#!/usr/bin/env php
<?php

/**
 * Script standalone pour tester l'authentification Basic Auth avec DHL.
 * 
 * Ce script teste l'authentification Basic Auth directement comme dans le Swagger.
 * 
 * Utilisation :
 *   php test-auth.php
 *   php test-auth.php --account=default
 *   php test-auth.php --account=default --verbose
 */

require __DIR__ . '/vendor/autoload.php';

use App\Config\AppConfig;
use App\Factory\DhlClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

// Configuration par défaut
$accountName = 'default';
$verbose = false;

// Parser les arguments de ligne de commande
$args = array_slice($argv, 1);
foreach ($args as $arg) {
    if (str_starts_with($arg, '--account=')) {
        $accountName = substr($arg, 10);
    } elseif ($arg === '--verbose' || $arg === '-v') {
        $verbose = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage: php test-auth.php [OPTIONS]\n";
        echo "\n";
        echo "Options:\n";
        echo "  --account=NAME    Account name from config/accounts.php (default: default)\n";
        echo "  --verbose, -v     Show detailed information\n";
        echo "  --help, -h        Show this help message\n";
        echo "\n";
        echo "Examples:\n";
        echo "  php test-auth.php\n";
        echo "  php test-auth.php --account=default\n";
        echo "  php test-auth.php --account=default --verbose\n";
        exit(0);
    }
}

// Fonction pour afficher les messages colorés
function printSuccess(string $message): void
{
    echo "\033[32m✓\033[0m $message\n";
}

function printError(string $message): void
{
    echo "\033[31m✗\033[0m $message\n";
}

function printInfo(string $message): void
{
    echo "\033[36mℹ\033[0m $message\n";
}

function printWarning(string $message): void
{
    echo "\033[33m⚠\033[0m $message\n";
}

// Charger la configuration
$configPath = __DIR__ . '/config/accounts.php';
try {
    $appConfig = new AppConfig($configPath);
} catch (\InvalidArgumentException $e) {
    printError("Configuration error: " . $e->getMessage());
    exit(1);
}

// Vérifier que le compte existe
if (!$appConfig->hasAccount($accountName)) {
    printError("Account '$accountName' not found in configuration.");
    echo "\nAvailable accounts: " . implode(', ', array_keys($appConfig->getAccounts())) . "\n";
    exit(1);
}

// Récupérer la configuration du compte
$account = $appConfig->getAccount($accountName);

// Afficher les informations de configuration (masquées)
echo "Testing DHL Basic Auth Authentication\n";
echo "=====================================\n\n";
printInfo("Account: $accountName");
printInfo("Base URL: " . ($account['base_url'] ?? 'N/A'));
printInfo("Site ID: " . (isset($account['site_id']) ? substr($account['site_id'], 0, 4) . '***' : 'N/A'));
echo "\n";

// Vérifier que les credentials sont présents
if (empty($account['base_url'])) {
    printError("Account configuration missing 'base_url'.");
    exit(1);
}

if (empty($account['site_id']) || empty($account['password'])) {
    printError("Account configuration missing 'site_id' or 'password'.");
    exit(1);
}

// Créer le client HTTP
$mode = getenv('DHL_MODE') ?: 'real';
$httpClient = DhlClientFactory::create($mode);

// Construire l'en-tête Authorization Basic Auth (comme dans le Swagger)
$credentials = base64_encode($account['site_id'] . ':' . $account['password']);
$authHeader = 'Basic ' . $credentials;

// Utiliser l'endpoint /address-validate pour tester Basic Auth (simple, ne dépend pas de produits/dates)
$testUrl = rtrim($account['base_url'], '/') . '/address-validate';

// Paramètres minimaux pour tester l'endpoint /address-validate (seulement 2 paramètres obligatoires)
$testParams = [
    'type' => 'pickup',           // Obligatoire : 'pickup' ou 'delivery'
    'countryCode' => 'FR',        // Obligatoire : code pays ISO 2 lettres
    // Paramètres optionnels (pour plus de précision)
    'postalCode' => '75001',
    'cityName' => 'Paris',
];

if ($verbose) {
    echo "Request details:\n";
    echo "  URL: $testUrl\n";
    echo "  Method: GET\n";
    echo "  Headers: Authorization: Basic " . substr($credentials, 0, 20) . "...\n";
    echo "  Query params: " . http_build_query($testParams) . "\n";
    echo "\n";
}

// Effectuer l'appel avec Basic Auth direct
try {
    printInfo("Sending Basic Auth request to /address-validate endpoint...");
    
    $response = $httpClient->get($testUrl, [
        RequestOptions::QUERY => $testParams,
        RequestOptions::HEADERS => [
            'Authorization' => $authHeader,
        ],
    ]);
    
    // Décoder la réponse
    $statusCode = $response->getStatusCode();
    $responseBody = (string) $response->getBody();
    
    try {
        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        printError("Invalid JSON response: " . $e->getMessage());
        if ($verbose) {
            echo "Response body: $responseBody\n";
        }
        exit(1);
    }
    
    // Vérifier le statut HTTP
    if ($statusCode !== 200) {
        printError("Authentication failed with HTTP status $statusCode");
        if ($verbose) {
            echo "Response: $responseBody\n";
        }
        exit(1);
    }
    
    // Afficher le succès
    echo "\n";
    printSuccess("Basic Auth authentication successful!");
    echo "\n";
    
    // Afficher les détails de la réponse
    echo "Response Details:\n";
    echo "  HTTP Status  : $statusCode\n";
    
    if (isset($data['address'])) {
        echo "  Address      : Validated successfully\n";
        if (isset($data['address']['postalCode'])) {
            echo "  Postal Code  : {$data['address']['postalCode']}\n";
        }
        if (isset($data['address']['cityName'])) {
            echo "  City         : {$data['address']['cityName']}\n";
        }
    } elseif (isset($data['warnings']) || isset($data['addresses'])) {
        echo "  Validation   : Response received\n";
    }
    
    if ($verbose) {
        echo "\n";
        echo "Full Response:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
    
    echo "\n";
    printSuccess("Your DHL Basic Auth credentials are valid and working correctly!");
    
} catch (GuzzleException $e) {
    echo "\n";
    printError("Authentication failed!");
    echo "\n";
    
    $errorMessage = $e->getMessage();
    echo "Error: $errorMessage\n";
    
    // Essayer d'obtenir plus de détails depuis la réponse
    if (method_exists($e, 'getResponse')) {
        $errorResponse = $e->getResponse();
        if ($errorResponse !== null) {
            $statusCode = $errorResponse->getStatusCode();
            $errorBody = (string) $errorResponse->getBody();
            
            echo "\n";
            echo "HTTP Status: $statusCode\n";
            
            if (!empty($errorBody)) {
                echo "Response: $errorBody\n";
            }
            
            // Suggestions selon le code d'erreur
            echo "\n";
            echo "Troubleshooting:\n";
            
            if ($statusCode === 401) {
                echo "  • Verify your site_id and password in config/accounts.php\n";
                echo "  • Check that credentials match your DHL developer portal\n";
                echo "  • Ensure credentials are for the correct environment (sandbox/production)\n";
                echo "  • Note: Basic Auth may not be supported in production - use OAuth 2.0 instead\n";
            } elseif ($statusCode === 404) {
                echo "  • Check your base_url in config/accounts.php\n";
                echo "  • Verify the API endpoint URL is correct\n";
                echo "  • Ensure you're using the correct environment (test vs production)\n";
            } else {
                echo "  • Check your network connection\n";
                echo "  • Verify the base_url is accessible\n";
                echo "  • Contact DHL support if the issue persists\n";
            }
        }
    }
    
    exit(1);
} catch (\Exception $e) {
    echo "\n";
    printError("Unexpected error: " . $e->getMessage());
    if ($verbose) {
        echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    }
    exit(1);
}
