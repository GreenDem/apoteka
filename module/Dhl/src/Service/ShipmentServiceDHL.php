<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\AppConfig;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service principal pour créer des expéditions DHL et générer des étiquettes.
 * Gère l'authentification Basic Auth (comme dans le Swagger), la construction des requêtes et l'appel à l'API DHL.
 */
class ShipmentServiceDHL
{
    /**
     * @param AppConfig $config Configuration des comptes DHL
     * @param ClientInterface $httpClient Client HTTP Guzzle pour les appels API
     */
    public function __construct(
        private readonly AppConfig $config,
        private readonly ClientInterface $httpClient,
    ) {
    }

    /**
     * Crée une expédition DHL à partir d'un fichier JSON.
     *
     * @param string $accountName Nom du compte DHL à utiliser (défini dans config/accounts.php)
     * @param string $inputFile Chemin vers le fichier JSON contenant les données d'expédition
     * @param array<string, mixed> $overrides Valeurs à surcharger (serviceCode, labelFormat)
     * @return array{trackingNumber: string, labelContent: string, request: array, response: array}
     * @throws InvalidArgumentException Si le fichier est invalide ou le JSON mal formé
     * @throws RuntimeException Si l'appel API échoue
     */
    public function createShipmentFromFile(string $accountName, string $inputFile, array $overrides = []): array
    {
        $payload = $this->loadPayloadFromFile($inputFile);
        return $this->createShipment($accountName, $payload, $overrides);
    }

    /**
     * Crée une expédition DHL à partir d'un tableau de données.
     *
     * @param string $accountName Nom du compte DHL à utiliser
     * @param array<string, mixed> $payload Données de l'expédition (shipper, receiver, packages, etc.)
     * @param array<string, mixed> $overrides Valeurs à surcharger (serviceCode, labelFormat)
     * @return array{trackingNumber: string, labelContent: string, request: array, response: array}
     *         - trackingNumber : Numéro de suivi DHL
     *         - labelContent : Contenu PDF de l'étiquette en base64
     *         - request : Requête envoyée à DHL (pour debug)
     *         - response : Réponse complète de DHL (pour debug)
     * @throws InvalidArgumentException Si les données sont invalides
     * @throws RuntimeException Si l'appel API échoue ou la réponse est invalide
     */
    public function createShipment(string $accountName, array $payload, array $overrides = []): array
    {
        // Récupérer la configuration du compte
        $account = $this->config->getAccount($accountName);

        // Construire la requête API DHL
        $request = $this->buildShipmentRequest($payload, $account, $overrides);

        // Utiliser Basic Auth directement (comme dans le Swagger)
        $response = $this->sendShipmentRequest($account, $request);

        // Vérifier s'il y a des erreurs dans la réponse
        if (isset($response['warnings']) && is_array($response['warnings']) && count($response['warnings']) > 0) {
            $warnings = array_map(function($warning) {
                return $warning['message'] ?? (is_string($warning) ? $warning : json_encode($warning));
            }, $response['warnings']);
            throw new RuntimeException('DHL API returned warnings: ' . implode('; ', $warnings));
        }

        if (isset($response['errors']) && is_array($response['errors']) && count($response['errors']) > 0) {
            $errors = array_map(function($error) {
                return $error['message'] ?? (is_string($error) ? $error : json_encode($error));
            }, $response['errors']);
            throw new RuntimeException('DHL API returned errors: ' . implode('; ', $errors));
        }

        // Vérifier la structure de la réponse
        if (!isset($response['packages']) || !is_array($response['packages']) || empty($response['packages'])) {
            $errorDetails = [];
            if (isset($response['detail'])) {
                $errorDetails[] = 'Detail: ' . $response['detail'];
            }
            if (isset($response['additionalDetails']) && is_array($response['additionalDetails'])) {
                $errorDetails[] = 'Additional: ' . implode('; ', $response['additionalDetails']);
            }
            if (isset($response['message'])) {
                $errorDetails[] = 'Message: ' . $response['message'];
            }
            
            $errorMsg = 'DHL response does not contain packages.';
            if (!empty($errorDetails)) {
                $errorMsg .= ' ' . implode(' ', $errorDetails);
            }
            $errorMsg .= ' Response structure: ' . json_encode(array_keys($response));
            
            throw new RuntimeException($errorMsg);
        }

        // Selon le Swagger, shipmentTrackingNumber est au niveau racine de la réponse
        if (!isset($response['shipmentTrackingNumber'])) {
            $errorMsg = 'DHL response does not contain a shipmentTrackingNumber.';
            $errorMsg .= ' Response keys: ' . json_encode(array_keys($response));
            throw new RuntimeException($errorMsg);
        }
        
        $tracking = $response['shipmentTrackingNumber'];

        // Chercher le label dans documents[] selon la structure standard DHL (Swagger 3.1.1)
        // La structure officielle est : documents[] avec typeCode='label'
        $labelContent = null;
        
        if (!isset($response['documents']) || !is_array($response['documents'])) {
            $errorMsg = 'DHL response does not contain a "documents" array.';
            $errorMsg .= ' Response keys: ' . json_encode(array_keys($response));
            throw new RuntimeException($errorMsg);
        }

        // Chercher le premier document avec typeCode='label'
        foreach ($response['documents'] as $document) {
            if (isset($document['typeCode']) && $document['typeCode'] === 'label' && isset($document['content'])) {
                $labelContent = $document['content'];
                break; // Prendre le premier label trouvé
            }
        }

        if ($labelContent === null) {
            // Lister les types de documents disponibles pour aider au debug
            $documentTypes = array_map(function($doc) {
                return $doc['typeCode'] ?? 'unknown';
            }, $response['documents']);
            
            $errorMsg = 'DHL response does not contain a label document.';
            $errorMsg .= ' Available document types: ' . implode(', ', $documentTypes);
            $errorMsg .= ' (Total documents: ' . count($response['documents']) . ')';
            throw new RuntimeException($errorMsg);
        }

        return [
            'trackingNumber' => $tracking,
            'labelContent' => $labelContent,
            'request' => $request,
            'response' => $response,
        ];
    }

    /**
     * Construit la requête API DHL à partir des données d'expédition.
     *
     * @param array<string, mixed> $payload Données d'expédition (shipper, receiver, packages)
     * @param array<string, mixed> $account Configuration du compte DHL
     * @param array<string, mixed> $overrides Valeurs à surcharger
     * @return array<string, mixed> Requête formatée pour l'API DHL
     * @throws InvalidArgumentException Si les données obligatoires sont manquantes
     */
    private function buildShipmentRequest(array $payload, array $account, array $overrides): array
    {
        // Extraire les données obligatoires
        $shipper = $payload['shipper'] ?? null;
        $receiver = $payload['receiver'] ?? null;
        $packages = $payload['packages'] ?? null;

        // Valider la présence des données obligatoires
        if (!is_array($shipper)) {
            throw new InvalidArgumentException('Payload must contain a "shipper" object.');
        }

        if (!is_array($receiver)) {
            throw new InvalidArgumentException('Payload must contain a "receiver" object.');
        }

        if (!is_array($packages) || $packages === []) {
            throw new InvalidArgumentException('Payload must contain at least one package.');
        }

        // Déterminer les valeurs avec priorité : overrides > payload > défaut
        $plannedDateTimeRaw = $payload['plannedShippingDateTime'] ?? null;
        $plannedDateTime = $plannedDateTimeRaw ? $this->normalizeDateTime($plannedDateTimeRaw) : $this->defaultPlannedDate();
        $serviceCode = $overrides['serviceCode'] ?? $payload['serviceCode'] ?? 'P'; // P = Express Worldwide par défaut
        $labelFormat = $overrides['labelFormat'] ?? $payload['labelFormat'] ?? 'A4'; // A4 par défaut
        
        // Mapper le format simplifié vers les templates DHL officiels
        $templateName = match($labelFormat) {
            'A4' => 'ECOM26_84_A4_001',
            'A6' => 'ECOM26_A6_002',
            default => 'ECOM26_84_001', // Template par défaut
        };

        return [
            'plannedShippingDateAndTime' => $plannedDateTime,
            'pickup' => [
                'isRequested' => false, // Par défaut, pas de pickup demandé
            ],
            'productCode' => $serviceCode,
            'accounts' => [
                [
                    'typeCode' => 'shipper',
                    'number' => $account['account_number'] ?? '',
                ],
            ],
            'customerDetails' => [
                'shipperDetails' => $this->buildPartyDetails($shipper, 'shipper'),
                'receiverDetails' => $this->buildPartyDetails($receiver, 'receiver'),
            ],
            // Structure conforme à l'API DHL Express 3.1.1
            'content' => [
                'packages' => $this->buildPackages($packages),
                'isCustomsDeclarable' => $payload['isCustomsDeclarable'] ?? false,
                'description' => $this->buildContentDescription($packages),
                'unitOfMeasurement' => 'metric', // Obligatoire selon le Swagger
            ],
            // Propriétés d'image de sortie selon la spec DHL Express API
            'outputImageProperties' => [
                'encodingFormat' => 'pdf',
                'imageOptions' => [
                    [
                        'typeCode' => 'label',
                        'templateName' => $templateName,
                        'renderDHLLogo' => true,
                        'fitLabelsToA4' => ($labelFormat === 'A4'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Construit les détails d'une partie (expéditeur ou destinataire) pour l'API DHL.
     *
     * @param array<string, mixed> $party Données de la partie (shipper ou receiver)
     * @param string $type Type de partie ('shipper' ou 'receiver') pour les messages d'erreur
     * @return array<string, mixed> Détails formatés pour l'API DHL
     * @throws InvalidArgumentException Si un champ obligatoire est manquant
     */
    private function buildPartyDetails(array $party, string $type): array
    {
        // Champs obligatoires pour l'API DHL
        $required = ['name', 'address1', 'city', 'postalCode', 'countryCode'];
        foreach ($required as $field) {
            if (empty($party[$field])) {
                throw new InvalidArgumentException(sprintf('Field "%s" is required in %s details.', $field, $type));
            }
        }

        $details = [
            'postalAddress' => [
                'addressLine1' => (string) $party['address1'],
                'cityName' => (string) $party['city'], // cityName au lieu de city selon le Swagger
                'postalCode' => (string) $party['postalCode'],
                'countryCode' => strtoupper((string) $party['countryCode']),
            ],
        ];

        if (!empty($party['address2'])) {
            $details['postalAddress']['addressLine2'] = (string) $party['address2'];
        }

        // ContactInformation est obligatoire avec companyName et fullName selon le Swagger
        $contact = [
            'companyName' => (string) ($party['name'] ?? ''),
            'fullName' => (string) ($party['name'] ?? ''),
        ];
        if (!empty($party['phone'])) {
            $contact['phone'] = (string) $party['phone'];
        }
        if (!empty($party['email'])) {
            $contact['email'] = (string) $party['email'];
        }
        $details['contactInformation'] = $contact;

        return $details;
    }

    /**
     * Construit le tableau des colis pour l'API DHL.
     *
     * @param array<int, array<string, mixed>> $packages Liste des colis
     * @return array<int, array<string, mixed>> Colis formatés pour l'API DHL
     * @throws InvalidArgumentException Si un colis est invalide ou manque le poids
     */
    private function buildPackages(array $packages): array
    {
        $result = [];
        foreach ($packages as $index => $package) {
            if (!is_array($package)) {
                throw new InvalidArgumentException(sprintf('Package at index %d must be an object.', $index));
            }

            // Le poids est obligatoire
            if (empty($package['weight'])) {
                throw new InvalidArgumentException(sprintf('Package at index %d must contain a weight.', $index));
            }

            // Construire le format attendu par l'API DHL (selon le Swagger)
            // weight est un nombre direct, pas un objet
            // dimensions n'a pas de unit
            $result[] = [
                'weight' => (float) $package['weight'], // Poids en kilogrammes (nombre direct)
                'dimensions' => [
                    'length' => (float) ($package['length'] ?? 10), // Dimensions par défaut 10x10x10 cm si non spécifiées
                    'width' => (float) ($package['width'] ?? 10),
                    'height' => (float) ($package['height'] ?? 10),
                    // Pas de 'unit' dans dimensions selon le Swagger
                ],
                // DHL exige une description non vide (minLength: 1, ne peut pas être vide ou seulement des espaces)
                'description' => !empty(trim((string) ($package['description'] ?? ''))) 
                    ? trim((string) $package['description']) 
                    : 'Commercial goods',
            ];
        }

        return $result;
    }

    /**
     * Construit la description du contenu à partir des descriptions des colis.
     *
     * @param array<int, array<string, mixed>> $packages Liste des colis
     * @return string Description du contenu (concaténation des descriptions ou 'Commercial goods' par défaut)
     */
    private function buildContentDescription(array $packages): string
    {
        $descriptions = [];
        foreach ($packages as $package) {
            if (isset($package['description']) && $package['description'] !== '') {
                $descriptions[] = (string) $package['description'];
            }
        }

        // Si aucune description n'est fournie, utiliser la valeur par défaut DHL
        if ($descriptions === []) {
            return 'Commercial goods';
        }

        return implode(', ', $descriptions);
    }

    /**
     * Envoie la requête de création d'expédition à l'API DHL avec Basic Auth.
     *
     * @param array<string, mixed> $account Configuration du compte DHL (doit contenir base_url, site_id, password)
     * @param array<string, mixed> $request Requête formatée pour l'API DHL
     * @return array<string, mixed> Réponse de l'API DHL contenant le tracking number et le label
     * @throws InvalidArgumentException Si l'URL de base est invalide ou les credentials manquants
     * @throws RuntimeException Si l'appel API échoue
     */
    private function sendShipmentRequest(array $account, array $request): array
    {
        // Construire l'URL complète pour l'endpoint shipments
        $apiUrl = rtrim((string) ($account['base_url'] ?? ''), '/') . '/shipments';
        if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Account configuration must contain a valid "base_url".');
        }

        // Vérifier que les credentials sont présents
        $siteId = $account['site_id'] ?? '';
        $password = $account['password'] ?? '';
        
        if (empty($siteId) || empty($password)) {
            throw new InvalidArgumentException('Account configuration must contain both "site_id" and "password" for Basic Auth authentication.');
        }

        // Construire l'en-tête Authorization Basic Auth (comme dans le Swagger)
        $credentials = base64_encode($siteId . ':' . $password);
        $authHeader = 'Basic ' . $credentials;

        try {
            // Envoyer la requête POST avec Basic Auth directement
            $response = $this->httpClient->post($apiUrl, [
                RequestOptions::JSON => $request, // Corps de la requête en JSON
                RequestOptions::HEADERS => [
                    'Authorization' => $authHeader, // Basic Auth (comme dans le Swagger)
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $exception) {
            $message = 'DHL shipment request failed: ' . $exception->getMessage();
            // Ajouter le corps de la réponse d'erreur si disponible
            if ($exception->getResponse()) {
                $message .= ' - ' . $exception->getResponse()->getBody();
            }
            throw new RuntimeException($message, 0, $exception);
        }

        return $this->decodeResponse((string) $response->getBody());
    }

    /**
     * Normalise une date au format attendu par DHL.
     *
     * @param string $dateTimeString Date au format ISO 8601 ou autre format
     * @return string Date au format DHL (ex: 2025-12-02T10:00:00 GMT+00:00)
     */
    private function normalizeDateTime(string $dateTimeString): string
    {
        try {
            // Si déjà au format DHL, retourner tel quel
            if (str_contains($dateTimeString, ' GMT')) {
                return $dateTimeString;
            }
            
            // Parser la date (supporte plusieurs formats)
            $dateTime = new \DateTimeImmutable($dateTimeString);
            
            // Convertir en UTC si nécessaire
            if ($dateTime->getTimezone()->getName() !== 'UTC') {
                $dateTime = $dateTime->setTimezone(new \DateTimeZone('UTC'));
            }
            
            // Format attendu par DHL : '2022-11-11T19:19:40 GMT+00:00'
            return $dateTime->format('Y-m-d\TH:i:s \G\M\T+00:00');
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid date format: ' . $dateTimeString . '. Expected format: YYYY-MM-DDTHH:MM:SSZ or YYYY-MM-DDTHH:MM:SS GMT+00:00');
        }
    }

    /**
     * Génère une date d'expédition par défaut (demain à 10h00 UTC).
     *
     * @return string Date au format DHL (ex: 2025-12-02T10:00:00 GMT+00:00)
     */
    private function defaultPlannedDate(): string
    {
        $dateTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $dateTime = $dateTime->modify('+1 day') ?: $dateTime; // Demain

        // Fixer l'heure à 10h00 UTC
        // Format attendu par DHL : '2022-11-11T19:19:40 GMT+00:00'
        return $dateTime->setTime(10, 0, 0)->format('Y-m-d\TH:i:s \G\M\T+00:00');
    }

    /**
     * Récupère les produits DHL disponibles pour une expédition depuis un fichier JSON.
     *
     * @param string $accountName Nom du compte DHL à utiliser
     * @param string $inputFile Chemin vers le fichier JSON contenant les données d'expédition
     * @return array<string, mixed> Réponse de l'API DHL contenant les produits disponibles
     * @throws InvalidArgumentException Si le fichier est invalide ou le JSON mal formé
     * @throws RuntimeException Si l'appel API échoue
     */
    public function getAvailableProductsFromFile(string $accountName, string $inputFile): array
    {
        $payload = $this->loadPayloadFromFile($inputFile);
        return $this->getAvailableProducts($accountName, $payload);
    }

    /**
     * Récupère les produits DHL disponibles pour une expédition.
     *
     * @param string $accountName Nom du compte DHL à utiliser
     * @param array<string, mixed> $payload Données de l'expédition (shipper, receiver, packages)
     * @return array<string, mixed> Réponse de l'API DHL contenant les produits disponibles
     * @throws InvalidArgumentException Si les données sont invalides
     * @throws RuntimeException Si l'appel API échoue
     */
    public function getAvailableProducts(string $accountName, array $payload): array
    {
        $account = $this->config->getAccount($accountName);
        
        // Extraire les données nécessaires
        $shipper = $payload['shipper'] ?? null;
        $receiver = $payload['receiver'] ?? null;
        $packages = $payload['packages'] ?? null;

        if (!is_array($shipper) || !is_array($receiver) || !is_array($packages) || empty($packages)) {
            throw new InvalidArgumentException('Payload must contain shipper, receiver, and at least one package.');
        }

        // Prendre le premier colis pour /products (endpoint pour un seul colis)
        $firstPackage = $packages[0];
        
        // Calculer la date d'expédition prévue (doit être dans le futur)
        $plannedDate = $this->calculatePlannedShippingDate($payload);

        // Construire les paramètres de requête
        $queryParams = [
            'accountNumber' => $account['account_number'] ?? '',
            'originCountryCode' => $shipper['countryCode'] ?? '',
            'originPostalCode' => $shipper['postalCode'] ?? '',
            'originCityName' => $shipper['city'] ?? '',
            'destinationCountryCode' => $receiver['countryCode'] ?? '',
            'destinationPostalCode' => $receiver['postalCode'] ?? '',
            'destinationCityName' => $receiver['city'] ?? '',
            'weight' => (float) ($firstPackage['weight'] ?? 1.0),
            'length' => (float) ($firstPackage['length'] ?? 10),
            'width' => (float) ($firstPackage['width'] ?? 10),
            'height' => (float) ($firstPackage['height'] ?? 10),
            'plannedShippingDate' => $plannedDate,
            'isCustomsDeclarable' => ($payload['isCustomsDeclarable'] ?? false) ? 'true' : 'false',
            'unitOfMeasurement' => 'metric',
        ];

        return $this->sendProductsRequest($account, $queryParams);
    }

    /**
     * Récupère les tarifs DHL pour une expédition depuis un fichier JSON.
     *
     * @param string $accountName Nom du compte DHL à utiliser
     * @param string $inputFile Chemin vers le fichier JSON contenant les données d'expédition
     * @return array<string, mixed> Réponse de l'API DHL contenant les tarifs disponibles
     * @throws InvalidArgumentException Si le fichier est invalide ou le JSON mal formé
     * @throws RuntimeException Si l'appel API échoue
     */
    public function getRatesFromFile(string $accountName, string $inputFile): array
    {
        $payload = $this->loadPayloadFromFile($inputFile);
        return $this->getRates($accountName, $payload);
    }

    /**
     * Récupère les tarifs DHL pour une expédition.
     *
     * @param string $accountName Nom du compte DHL à utiliser
     * @param array<string, mixed> $payload Données de l'expédition (shipper, receiver, packages)
     * @return array<string, mixed> Réponse de l'API DHL contenant les tarifs disponibles
     * @throws InvalidArgumentException Si les données sont invalides
     * @throws RuntimeException Si l'appel API échoue
     */
    public function getRates(string $accountName, array $payload): array
    {
        $account = $this->config->getAccount($accountName);
        
        // Extraire les données nécessaires
        $shipper = $payload['shipper'] ?? null;
        $receiver = $payload['receiver'] ?? null;
        $packages = $payload['packages'] ?? null;

        if (!is_array($shipper) || !is_array($receiver) || !is_array($packages) || empty($packages)) {
            throw new InvalidArgumentException('Payload must contain shipper, receiver, and at least one package.');
        }

        // Prendre le premier colis pour /rates GET (endpoint pour un seul colis)
        $firstPackage = $packages[0];
        
        // Calculer la date d'expédition prévue (doit être dans le futur)
        $plannedDate = $this->calculatePlannedShippingDate($payload);

        // Construire les paramètres de requête (identique à /products)
        $queryParams = [
            'accountNumber' => $account['account_number'] ?? '',
            'originCountryCode' => $shipper['countryCode'] ?? '',
            'originPostalCode' => $shipper['postalCode'] ?? '',
            'originCityName' => $shipper['city'] ?? '',
            'destinationCountryCode' => $receiver['countryCode'] ?? '',
            'destinationPostalCode' => $receiver['postalCode'] ?? '',
            'destinationCityName' => $receiver['city'] ?? '',
            'weight' => (float) ($firstPackage['weight'] ?? 1.0),
            'length' => (float) ($firstPackage['length'] ?? 10),
            'width' => (float) ($firstPackage['width'] ?? 10),
            'height' => (float) ($firstPackage['height'] ?? 10),
            'plannedShippingDate' => $plannedDate,
            'isCustomsDeclarable' => ($payload['isCustomsDeclarable'] ?? false) ? 'true' : 'false',
            'unitOfMeasurement' => 'metric',
        ];

        return $this->sendRatesRequest($account, $queryParams);
    }

    /**
     * Envoie une requête GET à un endpoint DHL (products ou rates).
     *
     * @param array<string, mixed> $account Configuration du compte DHL
     * @param string $endpoint Endpoint à appeler ('products' ou 'rates')
     * @param array<string, mixed> $queryParams Paramètres de requête
     * @return array<string, mixed> Réponse de l'API DHL
     * @throws RuntimeException Si l'appel API échoue
     */
    private function sendGetRequest(array $account, string $endpoint, array $queryParams): array
    {
        $apiUrl = rtrim((string) ($account['base_url'] ?? ''), '/') . '/' . $endpoint;
        if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Account configuration must contain a valid "base_url".');
        }

        $siteId = $account['site_id'] ?? '';
        $password = $account['password'] ?? '';
        
        if (empty($siteId) || empty($password)) {
            throw new InvalidArgumentException('Account configuration must contain both "site_id" and "password" for Basic Auth authentication.');
        }

        $credentials = base64_encode($siteId . ':' . $password);
        $authHeader = 'Basic ' . $credentials;

        try {
            $response = $this->httpClient->get($apiUrl, [
                RequestOptions::QUERY => $queryParams,
                RequestOptions::HEADERS => [
                    'Authorization' => $authHeader,
                ],
            ]);
        } catch (GuzzleException $exception) {
            $message = "DHL {$endpoint} request failed: " . $exception->getMessage();
            if ($exception->getResponse()) {
                $message .= ' - ' . $exception->getResponse()->getBody();
            }
            throw new RuntimeException($message, 0, $exception);
        }

        return $this->decodeResponse((string) $response->getBody());
    }

    /**
     * Envoie une requête GET à l'endpoint /products de DHL.
     *
     * @param array<string, mixed> $account Configuration du compte DHL
     * @param array<string, mixed> $queryParams Paramètres de requête
     * @return array<string, mixed> Réponse de l'API DHL
     * @throws RuntimeException Si l'appel API échoue
     */
    private function sendProductsRequest(array $account, array $queryParams): array
    {
        return $this->sendGetRequest($account, 'products', $queryParams);
    }

    /**
     * Envoie une requête GET à l'endpoint /rates de DHL.
     *
     * @param array<string, mixed> $account Configuration du compte DHL
     * @param array<string, mixed> $queryParams Paramètres de requête
     * @return array<string, mixed> Réponse de l'API DHL
     * @throws RuntimeException Si l'appel API échoue
     */
    private function sendRatesRequest(array $account, array $queryParams): array
    {
        return $this->sendGetRequest($account, 'rates', $queryParams);
    }

    /**
     * Charge un payload depuis un fichier JSON.
     *
     * @param string $inputFile Chemin vers le fichier JSON
     * @return array<string, mixed> Données décodées
     * @throws InvalidArgumentException Si le fichier est invalide ou le JSON mal formé
     */
    private function loadPayloadFromFile(string $inputFile): array
    {
        // Chercher le fichier (avec fallback vers examples/)
        $resolvedFile = $this->resolveInputFile($inputFile);
        
        if (!is_file($resolvedFile) || !is_readable($resolvedFile)) {
            throw new InvalidArgumentException(sprintf('Input file "%s" is not readable.', $inputFile));
        }

        try {
            $payload = json_decode(
                file_get_contents($resolvedFile),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('Invalid JSON payload: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($payload)) {
            throw new InvalidArgumentException('Input payload must decode to an associative array.');
        }

        return $payload;
    }

    /**
     * Résout le chemin d'un fichier d'entrée (cherche dans examples/ si relatif).
     *
     * @param string $inputFile Chemin vers le fichier
     * @return string Chemin résolu
     */
    private function resolveInputFile(string $inputFile): string
    {
        // Si le fichier existe et est lisible, le retourner tel quel
        if (is_file($inputFile) && is_readable($inputFile)) {
            return $inputFile;
        }

        // Essayer dans examples/ si le chemin est relatif (pas un chemin absolu)
        $isAbsolute = str_starts_with($inputFile, DIRECTORY_SEPARATOR) 
            || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Z]:\\\\/', $inputFile));
        
        if (!$isAbsolute) {
            $examplesPath = __DIR__ . '/../../examples/' . basename($inputFile);
            if (is_file($examplesPath) && is_readable($examplesPath)) {
                return $examplesPath;
            }
        }

        return $inputFile;
    }

    /**
     * Calcule la date d'expédition prévue (doit être dans le futur, format YYYY-MM-DD).
     *
     * @param array<string, mixed> $payload Données de l'expédition
     * @return string Date au format YYYY-MM-DD
     */
    private function calculatePlannedShippingDate(array $payload): string
    {
        $plannedDate = $payload['plannedShippingDateTime'] ?? null;
        
        if ($plannedDate) {
            try {
                $dateTime = new \DateTimeImmutable($plannedDate);
                // Vérifier que la date n'est pas dans le passé
                $today = new \DateTimeImmutable('today');
                if ($dateTime < $today) {
                    // Si la date est dans le passé, utiliser demain
                    return date('Y-m-d', strtotime('+1 day'));
                }
                // Extraire seulement la date (YYYY-MM-DD)
                return $dateTime->format('Y-m-d');
            } catch (\Exception $e) {
                // En cas d'erreur de parsing, utiliser demain
                return date('Y-m-d', strtotime('+1 day'));
            }
        }
        
        // Par défaut, utiliser demain
        return date('Y-m-d', strtotime('+1 day'));
    }

    /**
     * Décode la réponse JSON de l'API DHL.
     *
     * @param string $body Corps de la réponse HTTP
     * @return array<string, mixed> Données décodées
     * @throws RuntimeException Si le JSON est invalide ou le format inattendu
     */
    private function decodeResponse(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Unable to decode DHL response: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected DHL response format.');
        }

        return $decoded;
    }
}

