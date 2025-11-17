<?php

declare(strict_types=1);

namespace Application\Model;

use DateTimeImmutable;
use JsonException;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Paginator\Paginator;
use Laminas\Paginator\Adapter\DbSelect;
use RuntimeException;

final class UserTable
{
    private TableGateway $tableGateway;
    private int $maxPageSize = 100; //limite sécurité contre abus a changer au besoin

    public function __construct(TableGateway $tableGateway)
    {
        $this->tableGateway = $tableGateway;
    }

    /**
     * Liste paginée des utilisateurs actifs.
    * @param int $page - Numéro de la page à afficher
    * @param int $pageSize - Nombre d'éléments par page max 100
    * @return User[]
    */
    public function fetchPage(int $page = 1, int $pageSize = 25): array
    {
        // Validation sécurité : page doit être >= 1
        $page = max(1, $page);
        // Validation sécurité : pageSize doit être >= 1 et <= 100
        $pageSize = max(1, min($pageSize, $this->maxPageSize));


         // creation de la requete SQL
        $select = new Select($this->tableGateway->getTable());
        $select->where(['deleted_at' => null]); // on ne récupère que les utilisateurs non supprimés
        $select->order('created_at DESC');

        // creation de l'adaptateur de pagination 
        //Dbselect deprecated depuis la version 2.19.0 de laminas/laminas-paginator mais pas encore remplacé par un autre adaptateur
        $adapter = new DbSelect(
            $select,
            $this->tableGateway->getAdapter(),
            $this->tableGateway->getResultSetPrototype()
        );
        
        // creation du paginateur
        $paginator = new Paginator($adapter);
        $paginator->setItemCountPerPage($pageSize);
        $paginator->setCurrentPageNumber($page);

        // retourne les éléments de la page courante
        return iterator_to_array($paginator->getCurrentItems(), false);
    }

    /**
     * Récupère un utilisateur par son ID
     * @param int $id - ID de l'utilisateur
     * @return User
     * @throws RuntimeException si l'utilisateur n'est pas trouvé ou supprimé
     */
    public function getUser(int $id): User
    {
        /** @var \Laminas\Db\ResultSet\HydratingResultSet $rowset */
        $rowset = $this->tableGateway->select([
            'id' => $id,
            'deleted_at' => null, // on ne récupère que les utilisateurs non supprimés
        ]);

        // recupération de l'utilisateur
        $user = $rowset->current();
        if (! $user) {
            throw new RuntimeException("Utilisateur $id introuvable ou supprimé.");
        }

        return $user;
    }

    /**
     * Récupère un utilisateur par son email
     * @param string $email - Email de l'utilisateur
     * @return User|null
     * @throws RuntimeException si l'utilisateur n'est pas trouvé ou supprimé
     */
    public function fetchByEmail(string $email): ?User
    {
        /** @var \Laminas\Db\ResultSet\HydratingResultSet $rowset */
        $rowset = $this->tableGateway->select([
            'email' => mb_strtolower(trim($email)),
            'deleted_at' => null,
        ]);

        return $rowset->current() ?: null;
    }

    /**
     * Enregistre ou met à jour un utilisateur
     * @param User $user - Utilisateur à enregistrer
     * @throws RuntimeException si l'utilisateur n'est pas trouvé ou supprimé
     */
    public function saveUser(User $user): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        // sérialisation des rôles utilisateur
        try {
            $rolesJson = json_encode($user->getRoles(), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Impossible de sérialiser les rôles utilisateur.', 0, $e);
        }

        // création du tableau des données à enregistrer
        $data = [
            'username'      => $user->getEmail(), // Préparation de la ROW
            'email'         => $user->getEmail(),
            'password_hash' => $user->getPasswordHash(),
            'roles'         => $rolesJson,
            'updated_at'    => $now,
            'deleted_at'    => $user->getDeletedAt(),
            'last_login_at' => $user->getLastLoginAt(),
        ];

        // si l'utilisateur n'a pas d'ID, on enregistre un nouvel utilisateur
        if ($user->getId() === null) {
            $data['created_at'] = $now;

            $this->tableGateway->insert($data);
            $user->setId((int) $this->tableGateway->getLastInsertValue());
            return;
        }
        // si l'utilisateur a un ID, on met à jour l'utilisateur
        $this->assertExists($user->getId());

        // mise à jour de l'utilisateur
        $this->tableGateway->update($data, ['id' => $user->getId()]);
    }

    public function touchLastLoginAt(int $userId): void
    {
        $this->tableGateway->update(
            ['last_login_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['id' => $userId]
        );
    }

    /**
     * Met à jour la date de suppression d'un utilisateur
     * @param int $targetId - ID de l'utilisateur à "supprimer"
     * @param string $reason - Raison de la suppression (optionnel)
     * @throws RuntimeException si l'utilisateur n'est pas trouvé ou supprimé
     */
    public function softDelete(int $targetId, ?string $reason = null): void
    {
        // vérification de l'existence de l'utilisateur
        $this->assertExists($targetId);

        // création du tableau des données à mettre à jour
        $payload = [
            'deleted_at'   => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'deleted_note' => $reason,
        ];

        // mise à jour de l'utilisateur
        $this->tableGateway->update($payload, ['id' => $targetId]);
    }

    /**
     * Supprime un utilisateur de manière irréversible de la base de données
     * @param int $targetId - ID de l'utilisateur à supprimer
     * @param int $currentUserId - ID de l'utilisateur en cours (pour éviter la suppression total de son propre compte - Admin pour la sécurité)
     * @throws RuntimeException si l'utilisateur n'est pas trouvé ou supprimé
     */
    public function deleteUser(int $targetId, int $currentUserId): void
    {
        // vérification de l'existence de l'utilisateur
        $this->assertExists($targetId);

        // vérification de la suppression de son propre compte
        if ($targetId === $currentUserId) {
            throw new RuntimeException('Impossible de supprimer votre propre compte.');
        }

        // suppression de l'utilisateur
        $this->tableGateway->delete(['id' => $targetId]);
    }

    /**
     * Vérifie si un utilisateur existe dans la base de données
     * @param int $id - ID de l'utilisateur à vérifier
     * @throws RuntimeException si l'utilisateur n'est pas trouvé
     */
    private function assertExists(int $id): void
    {
        /** @var \Laminas\Db\ResultSet\HydratingResultSet $rowset */
        $rowset = $this->tableGateway->select(['id' => $id]);
        if (! $rowset->current()) {
            throw new RuntimeException("Impossible d'accéder à l'utilisateur $id.");
        }
    }
}