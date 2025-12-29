# Intégration dans Laminas MVC

Guide pour intégrer le service DHL standalone dans une application Laminas MVC.

## Principe

Le service DHL est un standalone, il peut être utilisé directement sans configuration complexe. Instanciation simple des classes dans votre contrôleur.

## Utilisation basique

### Exemple de contrôleur

```php
<?php

namespace Application\Controller;

use App\Config\AppConfig;
use App\Factory\DhlClientFactory;
use App\Service\ShipmentServiceDHL;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;

class OrderController extends AbstractActionController
{
    public function createDhlLabelAction()
    {
        $orderId = (int) $this->params()->fromRoute('id');
        $order = $this->getOrderService()->getOrder($orderId);

        try {
            // Instancier le service DHL
            $configPath = __DIR__ . '/../../../../config/accounts.php';
            $appConfig = new AppConfig($configPath);
            $httpClient = DhlClientFactory::create('real');
            $shipmentService = new ShipmentServiceDHL($appConfig, $httpClient);

            // Construire le payload depuis la commande
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

            // Créer l'expédition DHL
            $result = $shipmentService->createShipment('default', $payload);

            // Sauvegarder le PDF
            $labelPath = $this->saveLabel($result['labelContent'], $result['trackingNumber']);
            
            // Mettre à jour la commande
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
```

## Structure du payload

Le payload doit contenir les champs suivants :

### Champs obligatoires

- `shipper` : objet avec les informations de l'expéditeur
  - `name` : nom de l'expéditeur
  - `address1` : adresse ligne 1
  - `city` : ville
  - `postalCode` : code postal
  - `countryCode` : code pays (2 caractères, ex: FR)
  
- `receiver` : objet avec les informations du destinataire (même structure que shipper)

- `packages` : tableau d'objets package
  - `weight` : poids en kilogrammes (obligatoire)
  - `length`, `width`, `height` : dimensions en centimètres (optionnel, défaut 10x10x10)
  - `description` : description du colis (optionnel)

### Champs optionnels

- `plannedShippingDateTime` : date d'expédition planifiée (format ISO 8601 avec Z)
- `serviceCode` : code service DHL (P, D, I, etc.), défaut: P
- `labelFormat` : format étiquette (A4 ou A6), défaut: A4
- `shipper.phone` : téléphone expéditeur
- `shipper.email` : email expéditeur
- `shipper.address2` : adresse ligne 2
- `receiver.phone` : téléphone destinataire
- `receiver.email` : email destinataire
- `receiver.address2` : adresse ligne 2

## Configuration

Le fichier `config/accounts.php` doit être accessible depuis votre application Laminas.

Chemin recommandé : à la racine du projet, au même niveau que `vendor/`.

Si le chemin est différent, ajustez la variable `$configPath` dans le contrôleur.

## Gestion des erreurs

Le service lève des exceptions en cas d'erreur :

- `InvalidArgumentException` : données invalides (champs manquants, format incorrect)
- `RuntimeException` : erreur API DHL (authentification, réponse invalide, erreur réseau)

Gérer ces exceptions dans un try/catch comme dans l'exemple.

## Résultat

La méthode `createShipment()` retourne un tableau avec :

- `trackingNumber` : numéro de suivi DHL
- `labelContent` : contenu du PDF en base64
- `request` : requête envoyée à DHL (pour debug)
- `response` : réponse complète de DHL (pour debug)

## Sauvegarde du PDF

Le PDF est retourné en base64. Pour le sauvegarder :

```php
$pdfContent = base64_decode($result['labelContent']);
file_put_contents($filepath, $pdfContent);
```

## Exemple avec service métier

Si vous préférez isoler la logique dans un service :

```php
<?php

namespace Application\Service;

use App\Config\AppConfig;
use App\Factory\DhlClientFactory;
use App\Service\ShipmentServiceDHL;

class DhlLabelService
{
    private ShipmentServiceDHL $shipmentService;

    public function __construct()
    {
        $configPath = __DIR__ . '/../../../../config/accounts.php';
        $appConfig = new AppConfig($configPath);
        $httpClient = DhlClientFactory::create('real');
        $this->shipmentService = new ShipmentServiceDHL($appConfig, $httpClient);
    }

    public function createLabelForOrder($order): array
    {
        $payload = $this->buildPayloadFromOrder($order);
        return $this->shipmentService->createShipment('default', $payload);
    }

    private function buildPayloadFromOrder($order): array
    {
        // Logique de transformation Order -> Payload DHL
        return [
            'shipper' => [...],
            'receiver' => [...],
            'packages' => [...],
        ];
    }
}
```

Puis dans le contrôleur :

```php
$dhlService = new DhlLabelService();
$result = $dhlService->createLabelForOrder($order);
```

## Notes

- Le service est standalone, pas besoin de configuration Laminas ServiceManager
- Instanciation directe des classes suffit
- Le mode peut être 'real' ou 'demo' (pour tests avec serveur mock)
- Le compte DHL est défini dans `config/accounts.php` (clé 'default' par défaut)

