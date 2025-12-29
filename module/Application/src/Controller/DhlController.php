<?php

namespace Application\Controller;

use App\Config\AppConfig;
use App\Factory\DhlClientFactory;
use App\Service\ShipmentServiceDHL;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;

class DhlController extends AbstractActionController
{
    /**
     * Récupère le service DHL (même logique que la CLI)
     */
    private function getShipmentService(): ShipmentServiceDHL
    {
        $configPath = __DIR__ . '/../../../../module/Dhl/config/accounts.php';
        $appConfig = new AppConfig($configPath);
        
        // Utiliser 'dev' pour désactiver la vérification SSL en développement
        // En production, utiliser 'real' et configurer correctement les certificats
        $mode = getenv('DHL_MODE') ?: 'dev';
        $httpClient = DhlClientFactory::create($mode);
        
        return new ShipmentServiceDHL($appConfig, $httpClient);
    }

    /**
     * Page principale - Formulaire de création d'étiquette
     */
    public function indexAction(): ViewModel
    {
        return new ViewModel([
            'pageTitle' => 'Créer une étiquette DHL',
        ]);
    }

    /**
     * Vérifier les tarifs et services disponibles (AJAX)
     */
    public function checkRatesAction()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();
        
        if (!($request instanceof Request) || !$request->isPost()) {
            if ($response instanceof Response) {
                $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
                $response->setContent(json_encode([
                    'success' => false,
                    'error' => 'Requête invalide',
                ]));
            }
            return $response;
        }

        $data = $request->getPost()->toArray();
        
        try {
            $shipmentService = $this->getShipmentService();

            // Construire le payload depuis le formulaire
            $payload = $this->buildPayloadFromForm($data);

            // Récupérer les tarifs (qui incluent aussi les produits)
            $rates = $shipmentService->getRates('default', $payload);

            // Formater les résultats pour l'affichage
            $formattedRates = $this->formatRatesForDisplay($rates);

            if ($response instanceof Response) {
                $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
                $response->setContent(json_encode([
                    'success' => true,
                    'rates' => $formattedRates,
                    'raw' => $rates, // Pour debug si nécessaire
                ]));
            }
            return $response;

        } catch (\Exception $e) {
            if ($response instanceof Response) {
                $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
                $response->setContent(json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]));
            }
            return $response;
        }
    }

    /**
     * Formater les tarifs pour l'affichage
     */
    private function formatRatesForDisplay(array $rates): array
    {
        $formatted = [];
        
        if (isset($rates['products']) && is_array($rates['products'])) {
            foreach ($rates['products'] as $product) {
                $formatted[] = [
                    'code' => $product['productCode'] ?? 'N/A',
                    'name' => $product['productName'] ?? 'Service inconnu',
                    'type' => $product['productTypeCode'] ?? '',
                    'price' => $this->extractPrice($product),
                    'currency' => $product['currencyCode'] ?? 'EUR',
                    'deliveryDate' => $product['deliveryDate'] ?? null,
                    'totalTransitDays' => $product['totalTransitDays'] ?? null,
                ];
            }
        }

        return $formatted;
    }

    /**
     * Extraire le prix depuis la structure DHL
     */
    private function extractPrice(array $product): ?float
    {
        if (isset($product['totalPrice'])) {
            if (is_array($product['totalPrice'])) {
                return isset($product['totalPrice'][0]['price']) 
                    ? (float) $product['totalPrice'][0]['price'] 
                    : null;
            }
            return (float) $product['totalPrice'];
        }
        return null;
    }

    /**
     * Traiter le formulaire et créer l'étiquette (utilise directement ShipmentServiceDHL)
     */
    public function createAction()
    {
        $request = $this->getRequest();
        
        if (!($request instanceof Request) || !$request->isPost()) {
            return $this->redirect()->toRoute('dhl');
        }

        $data = $request->getPost()->toArray();
        
        try {
            $shipmentService = $this->getShipmentService();

            // Construire le payload depuis le formulaire
            $payload = $this->buildPayloadFromForm($data);

            // Créer l'expédition (même méthode que la CLI)
            $overrides = [];
            // Le serviceCode est maintenant obligatoire (choisi par l'utilisateur)
            if (!empty($data['selectedServiceCode'])) {
                $overrides['serviceCode'] = $data['selectedServiceCode'];
            } elseif (!empty($data['serviceCode'])) {
                $overrides['serviceCode'] = $data['serviceCode'];
            }
            if (!empty($data['labelFormat'])) {
                $overrides['labelFormat'] = $data['labelFormat'];
            }

            $result = $shipmentService->createShipment('default', $payload, $overrides);

            // Sauvegarder le PDF
            $labelPath = $this->saveLabel($result['labelContent'], $result['trackingNumber']);

            // Retour JSON pour requêtes AJAX
            if (($request instanceof Request) && $request->isXmlHttpRequest()) {
                $jsonResult = new JsonModel([
                    'success' => true,
                    'trackingNumber' => $result['trackingNumber'],
                    'labelUrl' => $labelPath,
                    'message' => 'Étiquette créée avec succès',
                ]);
                $jsonResult->setTerminal(true);
                return $jsonResult;
            }

            // Redirection avec message de succès
            $this->flashMessenger()->addSuccessMessage(
                'Étiquette DHL créée avec succès. Numéro de suivi: ' . $result['trackingNumber']
            );
            return $this->redirect()->toRoute('dhl', ['action' => 'success'], [
                'query' => ['tracking' => $result['trackingNumber'], 'label' => $labelPath]
            ]);

        } catch (\Exception $e) {
            if (($request instanceof Request) && $request->isXmlHttpRequest()) {
                $jsonResult = new JsonModel([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
                $jsonResult->setTerminal(true);
                return $jsonResult;
            }

            $this->flashMessenger()->addErrorMessage('Erreur: ' . $e->getMessage());
            return $this->redirect()->toRoute('dhl');
        }
    }

    /**
     * Page de succès après création
     */
    public function successAction(): ViewModel
    {
        $tracking = $this->params()->fromQuery('tracking');
        $label = $this->params()->fromQuery('label');

        return new ViewModel([
            'trackingNumber' => $tracking,
            'labelUrl' => $label,
            'pageTitle' => 'Étiquette créée avec succès',
        ]);
    }

    /**
     * Construire le payload DHL depuis les données du formulaire
     */
    private function buildPayloadFromForm(array $data): array
    {
        $payload = [
            'shipper' => [
                'name' => $data['shipper_name'] ?? '',
                'address1' => $data['shipper_address1'] ?? '',
                'city' => $data['shipper_city'] ?? '',
                'postalCode' => $data['shipper_postalCode'] ?? '',
                'countryCode' => $data['shipper_countryCode'] ?? 'FR',
            ],
            'receiver' => [
                'name' => $data['receiver_name'] ?? '',
                'address1' => $data['receiver_address1'] ?? '',
                'city' => $data['receiver_city'] ?? '',
                'postalCode' => $data['receiver_postalCode'] ?? '',
                'countryCode' => $data['receiver_countryCode'] ?? 'FR',
            ],
            'packages' => [
                [
                    'weight' => (float) ($data['package_weight'] ?? 1.0),
                    'length' => (int) ($data['package_length'] ?? 10),
                    'width' => (int) ($data['package_width'] ?? 10),
                    'height' => (int) ($data['package_height'] ?? 10),
                    // DHL exige une description non vide (minLength: 1)
                    'description' => !empty(trim($data['package_description'] ?? '')) 
                        ? trim($data['package_description']) 
                        : 'Commercial goods',
                ],
            ],
        ];

        // Champs optionnels
        if (!empty($data['shipper_phone'])) {
            $payload['shipper']['phone'] = $data['shipper_phone'];
        }
        if (!empty($data['shipper_email'])) {
            $payload['shipper']['email'] = $data['shipper_email'];
        }
        if (!empty($data['receiver_phone'])) {
            $payload['receiver']['phone'] = $data['receiver_phone'];
        }
        if (!empty($data['receiver_email'])) {
            $payload['receiver']['email'] = $data['receiver_email'];
        }
        if (!empty($data['plannedShippingDateTime'])) {
            // Convertir datetime-local en format ISO 8601
            $dateTime = new \DateTime($data['plannedShippingDateTime']);
            $payload['plannedShippingDateTime'] = $dateTime->format('Y-m-d\TH:i:s\Z');
        }
        if (!empty($data['serviceCode'])) {
            $payload['serviceCode'] = $data['serviceCode'];
        }
        if (!empty($data['labelFormat'])) {
            $payload['labelFormat'] = $data['labelFormat'];
        }

        return $payload;
    }

    /**
     * Sauvegarder le label PDF
     */
    private function saveLabel(string $labelBase64, string $trackingNumber): string
    {
        $labelsDir = getcwd() . '/public/labels';
        if (!is_dir($labelsDir)) {
            mkdir($labelsDir, 0755, true);
        }
        
        $filepath = $labelsDir . '/' . $trackingNumber . '.pdf';
        file_put_contents($filepath, base64_decode($labelBase64));
        
        return '/labels/' . $trackingNumber . '.pdf';
    }
}