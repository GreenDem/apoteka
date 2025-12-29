<?php

declare(strict_types=1);

namespace App\Config;

use InvalidArgumentException;

/**
 * Chargeur de configuration pour les comptes DHL.
 * Lit le fichier config/accounts.php et permet d'accéder aux comptes configurés.
 */
final class AppConfig
{
    /**
     * Tableau associatif des comptes DHL (clé = nom du compte, valeur = configuration).
     *
     * @var array<string, array<string, mixed>>
     */
    private array $accounts;

    /**
     * Constructeur : charge la configuration depuis le fichier spécifié.
     *
     * @param string $configPath Chemin vers le fichier de configuration (config/accounts.php)
     * @throws InvalidArgumentException Si le fichier n'existe pas ou ne retourne pas un tableau valide
     */
    public function __construct(string $configPath)
    {
        if (!is_file($configPath)) {
            throw new InvalidArgumentException(sprintf('Configuration file "%s" not found.', $configPath));
        }

        $accounts = require $configPath;
        if (!is_array($accounts) || $accounts === []) {
            throw new InvalidArgumentException(sprintf('Configuration file "%s" must return a non-empty array.', $configPath));
        }

        $this->accounts = $accounts;
    }

    /**
     * Retourne tous les comptes configurés.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAccounts(): array
    {
        return $this->accounts;
    }

    /**
     * Retourne la configuration d'un compte spécifique.
     *
     * @param string $name Nom du compte (ex: 'default')
     * @return array<string, mixed> Configuration du compte (site_id, password, account_number, base_url, auth_url)
     * @throws InvalidArgumentException Si le compte n'existe pas
     */
    public function getAccount(string $name): array
    {
        if (!$this->hasAccount($name)) {
            throw new InvalidArgumentException(sprintf('Account "%s" is not defined in configuration.', $name));
        }

        return $this->accounts[$name];
    }

    /**
     * Vérifie si un compte existe dans la configuration.
     *
     * @param string $name Nom du compte
     * @return bool True si le compte existe, false sinon
     */
    public function hasAccount(string $name): bool
    {
        return isset($this->accounts[$name]);
    }
}

