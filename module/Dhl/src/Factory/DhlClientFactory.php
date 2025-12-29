<?php

declare(strict_types=1);

namespace App\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;

/**
 * Factory pour créer le client HTTP Guzzle configuré pour DHL.
 * Gère la vérification SSL selon le mode (real = vérification activée, autre = désactivée).
 */
final class DhlClientFactory
{
    /**
     * Crée un client Guzzle configuré pour l'API DHL.
     *
     * @param string $mode Mode d'utilisation : 'real' active la vérification SSL, autre valeur la désactive
     * @return ClientInterface Client HTTP Guzzle configuré
     */
    public static function create(string $mode = 'real'): ClientInterface
    {
        // Par défaut, vérification SSL activée uniquement en mode 'real'
        $verify = $mode === 'real';

        // Possibilité de surcharger via variable d'environnement DHL_HTTP_VERIFY
        $envOverride = getenv('DHL_HTTP_VERIFY');
        if ($envOverride !== false) {
            $normalized = filter_var($envOverride, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($normalized !== null) {
                $verify = $normalized;
            }
        }

        return new Client([
            RequestOptions::VERIFY => $verify, // Vérification du certificat SSL
            RequestOptions::TIMEOUT => 30, // Timeout de 30 secondes
        ]);
    }
}

