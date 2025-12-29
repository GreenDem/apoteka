# 🚀 Quick Start

Guide de démarrage rapide en 5 minutes.

## 1️⃣ Installation

```bash
composer install
```

## 2️⃣ Configuration

```bash
cp config/accounts.php.template config/accounts.php
```

Éditer `config/accounts.php` :
- `site_id` : Votre API Key DHL
- `password` : Votre API Secret DHL
- `account_number` : Votre numéro de compte DHL

## 3️⃣ Préparer le fichier d'expédition

**⚠️ IMPORTANT :** Modifier la date dans `examples/shipment.sample.json` :

```json
{
  "plannedShippingDateTime": "2025-01-20T10:00:00Z",  // ← Changer cette date (doit être dans le futur)
  "shipper": { ... },
  "receiver": { ... },
  "packages": [ ... ]
}
```

**Format de date :** `YYYY-MM-DDTHH:MM:SSZ`
- Exemple : `2025-01-20T10:00:00Z` (20 janvier 2025 à 10h00 UTC)
- La date doit être dans le futur (au moins demain)

## 4️⃣ Tester l'authentification (optionnel)

```bash
php test-auth.php
```

## 5️⃣ Vérifier les produits disponibles (recommandé)

```bash
php dhl.php --input=examples/shipment.sample.json --check-products
```

Cela affiche les services DHL disponibles pour votre route.

## 6️⃣ Créer le shipment

```bash
php dhl.php --input=examples/shipment.sample.json
```

Le label PDF sera généré dans le dossier `labels/`.

## 📋 Commandes principales

### Créer un shipment
```bash
php dhl.php --input=shipment.json
```

### Vérifier les produits
```bash
php dhl.php --input=shipment.json --check-products
```

### Vérifier les tarifs
```bash
php dhl.php --input=shipment.json --check-rates
```

### Choisir un produit interactivement
```bash
php dhl.php --input=shipment.json --select-product
```

### Changer le service code
```bash
php dhl.php --input=shipment.json --service-code=D
```

### Changer le format d'étiquette
```bash
php dhl.php --input=shipment.json --label-format=A6
```

## ⚠️ Points importants

1. **Date d'expédition** : Toujours dans le futur (modifier dans le JSON)
2. **Environnement** : Par défaut, utilise le sandbox DHL (`/test`)
3. **SSL** : Si erreur SSL, utiliser `export DHL_HTTP_VERIFY=false` (dev uniquement)

## 🆘 Problèmes courants

### "Product not available"
→ Vérifier les produits disponibles avec `--check-products`

### "SSL certificate problem"
→ Utiliser `export DHL_HTTP_VERIFY=false` (dev uniquement)

### "Authentication failed"
→ Tester avec `php test-auth.php`

## ✅ Checklist

- [ ] Composer installé
- [ ] Credentials DHL configurés dans `config/accounts.php`
- [ ] Date dans `shipment.sample.json` modifiée (futur)
- [ ] Test d'authentification réussi (`php test-auth.php`)
- [ ] Label généré avec succès

**Prêt à expédier !** 📦
