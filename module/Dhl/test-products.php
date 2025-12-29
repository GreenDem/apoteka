#!/usr/bin/env php
<?php

/**
 * Script standalone pour tester l'endpoint /products de DHL et voir les services disponibles.
 * 
 * Ce script utilise Basic Auth pour interroger l'endpoint /products et afficher
 * tous les produits/services DHL disponibles pour un compte et des adresses données.
 * 
 * Utilisation :
 *   php test-products.php
 *   php test-products.php --account=default
 *   php test-products.php --account=default --verbose
 *   php test-products.php --input=examples/shipment.sample.json
 */

require __DIR__ . '/vendor/autoload.php';

use App\Config\AppConfig;
use App\Factory\DhlClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

// Configuration par défaut
$accountName = 'default';
$verbose = false;
$inputFile = null;

// Parser les arguments de ligne de commande
$args = array_slice($argv, 1);
foreach ($args as $arg) {
    if (str_starts_with($arg, '--account=')) {
        $accountName = substr($arg, 10);
    } elseif (str_starts_with($arg, '--input=')) {
        $inputFile = substr($arg, 8);
    } elseif ($arg === '--verbose' || $arg === '-v') {
        $verbose = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage: php test-products.php [OPTIONS]\n";
        echo "\n";
        echo "Options:\n";
        echo "  --account=NAME    Account name from config/accounts.php (default: default)\n";
        echo "  --input=FILE      JSON file with shipment data (default: examples/shipment.sample.json)\n";
        echo "  --verbose, -v     Show detailed information\n";
        echo "  --help, -h        Show this help message\n";
        echo "\n";
        echo "Examples:\n";
        echo "  php test-products.php\n";
        echo "  php test-products.php --account=default\n";
        echo "  php test-products.php --input=examples/shipment.sample.json --verbose\n";
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

// Charger les données du fichier d'entrée ou utiliser les valeurs par défaut
$shipmentData = null;
if ($inputFile && file_exists($inputFile)) {
    try {
        $jsonContent = file_get_contents($inputFile);
        $shipmentData = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
    } catch (\Exception $e) {
        printError("Failed to read input file: " . $e->getMessage());
        exit(1);
    }
} elseif ($inputFile) {
    printError("Input file not found: $inputFile");
    exit(1);
}

// Valeurs par défaut (adresses belges)
$defaultData = [
    'shipper' => [
        'address1' => 'Avenue Louise 50',
        'city' => 'Bruxelles',
        'postalCode' => '1050',
        'countryCode' => 'BE',
    ],
    'receiver' => [
        'address1' => 'Rue de la Loi 10',
        'city' => 'Bruxelles',
        'postalCode' => '1000',
        'countryCode' => 'BE',
    ],
    'packages' => [
        [
            'weight' => 2.5,
            'length' => 30,
            'width' => 20,
            'height' => 15,
        ],
    ],
];

// Utiliser les données du fichier ou les valeurs par défaut
$shipper = $shipmentData['shipper'] ?? $defaultData['shipper'];
$receiver = $shipmentData['receiver'] ?? $defaultData['receiver'];
$packages = $shipmentData['packages'] ?? $defaultData['packages'];

// Prendre le premier colis pour /products (endpoint pour un seul colis)
$firstPackage = $packages[0] ?? $defaultData['packages'][0];

// Calculer la date d'expédition prévue (demain si aujourd'hui est un jour ouvrable)
$plannedDate = date('Y-m-d', strtotime('+1 day'));

// Afficher les informations de configuration
echo "Testing DHL Products Availability\n";
echo "==================================\n\n";
printInfo("Account: $accountName");
printInfo("Base URL: " . ($account['base_url'] ?? 'N/A'));
printInfo("Account Number: " . ($account['account_number'] ?? 'N/A'));
echo "\n";

// Afficher les paramètres utilisés
echo "Shipment Parameters:\n";
echo "  Origin      : {$shipper['city']} ({$shipper['postalCode']}), {$shipper['countryCode']}\n";
echo "  Destination : {$receiver['city']} ({$receiver['postalCode']}), {$receiver['countryCode']}\n";
echo "  Package     : {$firstPackage['weight']} kg, {$firstPackage['length']}x{$firstPackage['width']}x{$firstPackage['height']} cm\n";
echo "  Date        : $plannedDate\n";
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

if (empty($account['account_number'])) {
    printError("Account configuration missing 'account_number'.");
    exit(1);
}

// Créer le client HTTP
$mode = getenv('DHL_MODE') ?: 'real';
$httpClient = DhlClientFactory::create($mode);

// Construire l'en-tête Authorization Basic Auth
$credentials = base64_encode($account['site_id'] . ':' . $account['password']);
$authHeader = 'Basic ' . $credentials;

// Construire l'URL de l'endpoint /products
$productsUrl = rtrim($account['base_url'], '/') . '/products';

// Paramètres requis pour /products (selon le Swagger)
$queryParams = [
    'accountNumber' => $account['account_number'],
    'originCountryCode' => $shipper['countryCode'],
    'originPostalCode' => $shipper['postalCode'],
    'originCityName' => $shipper['city'],
    'destinationCountryCode' => $receiver['countryCode'],
    'destinationPostalCode' => $receiver['postalCode'],
    'destinationCityName' => $receiver['city'],
    'weight' => $firstPackage['weight'],
    'length' => $firstPackage['length'],
    'width' => $firstPackage['width'],
    'height' => $firstPackage['height'],
    'plannedShippingDate' => $plannedDate,
    'isCustomsDeclarable' => 'false', // BE -> BE = pas de douane
    'unitOfMeasurement' => 'metric',
];

if ($verbose) {
    echo "Request details:\n";
    echo "  URL: $productsUrl\n";
    echo "  Method: GET\n";
    echo "  Headers: Authorization: Basic " . substr($credentials, 0, 20) . "...\n";
    echo "  Query params:\n";
    foreach ($queryParams as $key => $value) {
        echo "    $key: $value\n";
    }
    echo "\n";
}

// Effectuer l'appel avec Basic Auth
try {
    printInfo("Sending request to /products endpoint...");
    
    $response = $httpClient->get($productsUrl, [
        RequestOptions::QUERY => $queryParams,
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
        printError("Request failed with HTTP status $statusCode");
        if (isset($data['detail'])) {
            echo "Error: {$data['detail']}\n";
        }
        if (isset($data['additionalDetails'])) {
            foreach ($data['additionalDetails'] as $detail) {
                echo "  - $detail\n";
            }
        }
        if ($verbose) {
            echo "\nFull response: $responseBody\n";
        }
        exit(1);
    }
    
    // Afficher le succès
    echo "\n";
    printSuccess("Products retrieved successfully!");
    echo "\n";
    
    // Afficher les produits disponibles
    if (isset($data['products']) && is_array($data['products'])) {
        $productCount = count($data['products']);
        echo "Available Products ($productCount):\n";
        echo str_repeat("=", 80) . "\n";
        
        foreach ($data['products'] as $index => $product) {
            $num = $index + 1;
            echo "\n[$num] ";
            
            // Code produit
            if (isset($product['productCode'])) {
                echo "Product Code: \033[1m{$product['productCode']}\033[0m";
            }
            if (isset($product['localProductCode'])) {
                echo " (Local: {$product['localProductCode']})";
            }
            echo "\n";
            
            // Nom du produit
            if (isset($product['productName'])) {
                echo "   Name: {$product['productName']}\n";
            }
            
            // Type de produit
            if (isset($product['productTypeCode'])) {
                echo "   Type: {$product['productTypeCode']}\n";
            }
            
            // Date de livraison estimée
            if (isset($product['deliveryDate'])) {
                echo "   Estimated Delivery: {$product['deliveryDate']}\n";
            }
            if (isset($product['deliveryTime'])) {
                echo "   Delivery Time: {$product['deliveryTime']}\n";
            }
            
            // Tarif (si disponible)
            if (isset($product['totalPrice'])) {
                $currency = $product['currencyCode'] ?? 'EUR';
                $price = is_array($product['totalPrice']) 
                    ? ($product['totalPrice'][0]['price'] ?? 'N/A')
                    : $product['totalPrice'];
                echo "   Price: $price $currency\n";
            }
            
            // Services à valeur ajoutée
            if (isset($product['valueAddedServices']) && is_array($product['valueAddedServices']) && count($product['valueAddedServices']) > 0) {
                echo "   Value Added Services: " . implode(', ', array_column($product['valueAddedServices'], 'serviceCode')) . "\n";
            }
        }
        
        echo "\n" . str_repeat("=", 80) . "\n";
        
        if ($productCount === 0) {
            printWarning("No products available for these parameters.");
            echo "\n";
            echo "Possible reasons:\n";
            echo "  • No products available for this route (origin -> destination)\n";
            echo "  • Account doesn't have access to these products\n";
            echo "  • Date is not valid (weekend/holiday)\n";
            echo "  • Package dimensions/weight outside acceptable range\n";
        } else {
            echo "\n";
            printSuccess("Found $productCount product(s) available for your shipment!");
            echo "\n";
            echo "Tip: Use one of these product codes in your shipment request.\n";
        }
    } else {
        printWarning("No products found in response.");
        if ($verbose) {
            echo "\nFull response:\n";
            echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    if ($verbose) {
        echo "\n";
        echo "Full Response:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
    
} catch (GuzzleException $e) {
    echo "\n";
    printError("Request failed!");
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
                try {
                    $errorData = json_decode($errorBody, true);
                    if (isset($errorData['detail'])) {
                        echo "Detail: {$errorData['detail']}\n";
                    }
                    if (isset($errorData['additionalDetails'])) {
                        echo "Additional Details:\n";
                        foreach ($errorData['additionalDetails'] as $detail) {
                            echo "  - $detail\n";
                        }
                    }
                    if ($verbose) {
                        echo "\nFull response: $errorBody\n";
                    }
                } catch (\Exception $ex) {
                    echo "Response: $errorBody\n";
                }
            }
            
            // Suggestions selon le code d'erreur
            echo "\n";
            echo "Troubleshooting:\n";
            
            if ($statusCode === 401) {
                echo "  • Verify your site_id and password in config/accounts.php\n";
                echo "  • Check that credentials match your DHL developer portal\n";
            } elseif ($statusCode === 400) {
                echo "  • Check that all required parameters are provided\n";
                echo "  • Verify account_number is valid\n";
                echo "  • Check date format (YYYY-MM-DD)\n";
            } elseif ($statusCode === 404) {
                echo "  • No products available for the requested criteria\n";
                echo "  • Try a different date (next business day)\n";
                echo "  • Check if route is supported (origin -> destination)\n";
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

