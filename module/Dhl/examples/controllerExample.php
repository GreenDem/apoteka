<?php

namespace Application\Controller;

use App\Config\AppConfig;
use App\Factory\DhlClientFactory;
use App\Service\ShipmentServiceDHL;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;

class OrderController extends AbstractActionController
{
    /**
     * Créer une étiquette DHL pour une commande
     */
    public function createDhlLabelAction()
    {
        $orderId = (int) $this->params()->fromRoute('id');
        $order = $this->getOrderService()->getOrder($orderId);

        try {
            // 1. Instancier directement (c'est un standalone !)
            $configPath = __DIR__ . '/../../../../config/accounts.php'; // Chemin vers votre config
            $appConfig = new AppConfig($configPath);
            $httpClient = DhlClientFactory::create('real');
            $shipmentService = new ShipmentServiceDHL($appConfig, $httpClient);

            // 2. Construire le payload depuis votre commande
            $payload = [
                'shipper' => [
                    'name' => $order->getBillingAddress()->getCompanyName(),
                    'address1' => $order->getBillingAddress()->getStreet(),
                    'city' => $order->getBillingAddress()->getCity(),
                    'postalCode' => $order->getBillingAddress()->getPostalCode(),
                    'countryCode' => $order->getBillingAddress()->getCountry()->getCode(),
                    'phone' => $order->getBillingAddress()->getPhone(),
                    'email' => $order->getBillingAddress()->getEmail(),
                ],
                'receiver' => [
                    'name' => $order->getShippingAddress()->getFullName(),
                    'address1' => $order->getShippingAddress()->getStreet(),
                    'city' => $order->getShippingAddress()->getCity(),
                    'postalCode' => $order->getShippingAddress()->getPostalCode(),
                    'countryCode' => $order->getShippingAddress()->getCountry()->getCode(),
                    'phone' => $order->getShippingAddress()->getPhone(),
                    'email' => $order->getCustomer()->getEmail(),
                ],
                'packages' => [
                    [
                        'weight' => $order->getTotalWeight() ?: 1.0,
                        'length' => 30,
                        'width' => 20,
                        'height' => 15,
                        'description' => 'Order #' . $order->getId(),
                    ],
                ],
            ];

            // 3. Créer l'expédition
            $result = $shipmentService->createShipment('default', $payload);

            // 4. Sauvegarder le PDF
            $labelPath = $this->saveLabel($result['labelContent'], $result['trackingNumber']);
            
            // 5. Mettre à jour la commande
            $order->setTrackingNumber($result['trackingNumber']);
            $order->setLabelPath($labelPath);
            $this->getOrderService()->updateOrder($order);

            return new JsonModel([
                'success' => true,
                'trackingNumber' => $result['trackingNumber'],
                'labelUrl' => $labelPath,
            ]);

        } catch (\Exception $e) {
            return new JsonModel([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

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