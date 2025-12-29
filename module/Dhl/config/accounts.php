<?php

/**
 * Configuration des comptes DHL.
 * 
 * Ce fichier définit les comptes DHL disponibles pour l'application.
 * Chaque compte contient les identifiants nécessaires pour s'authentifier auprès de l'API DHL.
 * 
 * Les valeurs peuvent être définies via variables d'environnement ou directement dans ce fichier.
 * 
 * @return array<string, array{site_id: string, password: string, account_number: string, base_url: string, auth_url: string}>
 *         Tableau associatif : clé = nom du compte, valeur = configuration du compte
 */
return [
    // Compte par défaut
    'default' => [
        // API Key DHL (site_id) - Récupéré depuis DHL_SITE_ID ou valeur par défaut
        'site_id'       => 'apJ8hJ6xY6sA0d',
        
        // API Secret DHL (password) - Récupéré depuis DHL_PASSWORD ou valeur par défaut
        'password'      => 'L#5oB$7qZ$0qC@4r',
        
        // Numéro de compte DHL - Récupéré depuis DHL_ACCOUNT_NUMBER ou valeur par défaut
        'account_number'=> getenv('DHL_ACCOUNT_NUMBER') ?: '272651858',
        
        // URL de base de l'API DHL (test ou production)
        // Récupéré depuis DHL_BASE_URL ou URL de test par défaut
        //Test : https://express.api.dhl.com/mydhlapi/test
        //Production : https://express.api.dhl.com/mydhlapi
        'base_url'      => getenv('DHL_BASE_URL') ?: 'https://express.api.dhl.com/mydhlapi/test',
        
        // URL d'authentification OAuth 2.0
        // Récupéré depuis DHL_AUTH_URL ou URL de test par défaut
        'auth_url'      => getenv('DHL_AUTH_URL') ?: 'https://express.api.dhl.com/mydhlapi/test',
    ],

    // Ajouter d'autres comptes ici si nécessaire
    // Exemple :
    // 'compte2' => [
    //     'site_id' => '...',
    //     'password' => '...',
    //     ...
    // ],
];

