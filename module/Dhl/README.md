# DHL Express API Integration

Application PHP standalone pour créer des expéditions DHL Express via l'API MyDHL (v3.1.1).

## 📋 Prérequis

- PHP 8.2+
- Composer
- Credentials DHL Express API ([Obtenir ici](https://developer.dhl.com/))

## 🚀 Installation

```bash
composer install
```

## ⚙️ Configuration

1. Copier le template de configuration :
```bash
cp config/accounts.php.template config/accounts.php
```

2. Éditer `config/accounts.php` avec vos credentials DHL :
```php
'default' => [
    'site_id'        => 'VOTRE_API_KEY',
    'password'       => 'VOTRE_API_SECRET',
    'account_number' => 'VOTRE_NUMERO_COMPTE',
    'base_url'       => 'https://express.api.dhl.com/mydhlapi/test', // Sandbox
],
```

## 📦 Utilisation

### Créer un shipment et générer un label

```bash
php dhl.php --input=examples/shipment.sample.json
```

**⚠️ Important :** Modifier la date dans `examples/shipment.sample.json` :
- La date `plannedShippingDateTime` doit être dans le futur
- Format : `YYYY-MM-DDTHH:MM:SSZ` (ex: `2025-01-20T10:00:00Z`)

### Vérifier les produits disponibles

```bash
php dhl.php --input=examples/shipment.sample.json --check-products
```

### Vérifier les tarifs

```bash
php dhl.php --input=examples/shipment.sample.json --check-rates
```

### Sélectionner un produit interactivement

```bash
php dhl.php --input=examples/shipment.sample.json --select-product
```

## 🔧 Options CLI

| Option | Raccourci | Description |
|--------|-----------|-------------|
| `--account` | `-a` | Compte à utiliser (défaut: `default`) |
| `--input` | `-i` | Fichier JSON de l'expédition |
| `--output-dir` | `-o` | Dossier de sortie pour les labels (défaut: `labels`) |
| `--service-code` | | Code service DHL (P, D, E, I, N, etc.) |
| `--label-format` | | Format étiquette (A4 ou A6) |
| `--check-products` | | Vérifier les produits disponibles |
| `--check-rates` | | Vérifier les tarifs |
| `--select-product` | | Sélectionner un produit interactivement |
| `--debug` | | Afficher la réponse complète en cas d'erreur |

## 📝 Format JSON

### Champs obligatoires

```json
{
  "shipper": {
    "name": "Nom Expéditeur",
    "address1": "Adresse ligne 1",
    "city": "Ville",
    "postalCode": "Code postal",
    "countryCode": "FR"
  },
  "receiver": {
    "name": "Nom Destinataire",
    "address1": "Adresse ligne 1",
    "city": "Ville",
    "postalCode": "Code postal",
    "countryCode": "FR"
  },
  "packages": [
    {
      "weight": 2.5
    }
  ]
}
```

### Champs optionnels

- `plannedShippingDateTime` : Date d'expédition (format ISO 8601, doit être dans le futur)
- `serviceCode` : Code service DHL (P, D, E, I, N, etc.)
- `labelFormat` : Format étiquette (A4 ou A6)
- `isCustomsDeclarable` : Déclaration douanière (true/false)
- `packages[].length`, `width`, `height` : Dimensions en cm
- `packages[].description` : Description du colis
- `*.phone`, `*.email`, `*.address2` : Informations de contact

## 🧪 Scripts de test

### Tester l'authentification

```bash
php test-auth.php
```

### Tester les produits disponibles

```bash
php test-products.php
php test-products.php --input=examples/shipment.sample.json
```

## 🔍 Codes services DHL

| Code | Service | Description |
|------|---------|-------------|
| `P` | Express Worldwide | International standard (défaut) |
| `D` | Express 12:00 | Livraison avant midi |
| `I` | Express 9:00 | Livraison avant 9h |
| `E` | Economy Select | Économique international |
| `N` | Domestic Express | Express domestique |

## 🛠️ Variables d'environnement

```bash
# Désactiver la vérification SSL (dev/test uniquement)
export DHL_HTTP_VERIFY=false

# Mode de fonctionnement
export DHL_MODE=dev  # ou 'real'
```

## 📚 Documentation

- **Swagger API** : `docs/dpdhl-express-api-3.1.1_swagger.yaml`
- **Guide d'intégration** : `docs/INTEGRATION_LAMINAS.md`
- **Exemples** : `examples/`

## 🆘 Dépannage

### Erreur SSL

```bash
export DHL_HTTP_VERIFY=false
php dhl.php --input=shipment.json
```

### Produit non disponible

Vérifier les produits disponibles :
```bash
php dhl.php --input=shipment.json --check-products
```

### Erreur d'authentification

Tester les credentials :
```bash
php test-auth.php
```

## 📄 Licence

MIT
