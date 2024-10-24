<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class PermissionPersister
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @internal only for use by the app-system
     */
    public function updatePrivileges(?Permissions $permissions, string $appId, bool $acceptPermissions, Context $context): void
    {
        $privileges = $permissions ? $permissions->asParsedPrivileges() : [];

        if ($acceptPermissions) {
            $this->addPrivileges($privileges, $appId, $context);

            return;
        }

        $this->requestPrivileges($privileges, $appId);
    }

    /**
     * todo: maybe we want the ability to accept only a few permissions
     */
    public function acceptPrivileges(string $appId, Context $context): void
    {
        $privileges = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT privileges, requested_privileges
                FROM `acl_role`
                WHERE id = (SELECT acl_role_id FROM app WHERE id = :id)
            SQL,
            ['id' => Uuid::fromHexToBytes($appId)]
        );

        if (\count($privileges) !== 1) {
            return;
        }

        $row = current($privileges);

        $existingPrivileges = json_decode($row['privileges'], true, \JSON_THROW_ON_ERROR);
        $requestedPrivileges = json_decode($row['requested_privileges'], true, \JSON_THROW_ON_ERROR);

        $new = array_merge($existingPrivileges, $requestedPrivileges);

        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE `acl_role`
                SET `privileges` = :privileges, `requested_privileges` = :requestedPrivileges
                WHERE id = (SELECT acl_role_id FROM app WHERE id = :id)
            SQL,
            [
                'privileges' => json_encode($new, \JSON_THROW_ON_ERROR),
                'requestedPrivileges' => json_encode([], \JSON_THROW_ON_ERROR),
                'id' => Uuid::fromHexToBytes($appId),
            ]
        );

        $this->eventDispatcher->dispatch(new AppPermissionsUpdated($appId, $new, $context));
    }

    /**
     * @internal only for use by the app-system
     */
    public function removeRole(string $roleId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `acl_role` WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($roleId),
            ]
        );
    }

    public function softDeleteRole(string $roleId): void
    {
        $this->connection->executeStatement(
            'UPDATE `acl_role` SET `deleted_at` = :datetime WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($roleId),
                'datetime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    /**
     * @param array<string> $privileges
     */
    private function requestPrivileges(array $privileges, string $appId): void
    {
        $existingPrivileges = $this->connection->fetchOne(
            'SELECT privileges FROM `acl_role` WHERE id = (SELECT acl_role_id FROM app WHERE id = :id)',
            ['id' => Uuid::fromHexToBytes($appId)]
        );

        $existingPrivileges = json_decode($existingPrivileges, true, \JSON_THROW_ON_ERROR);

        sort($privileges);
        sort($existingPrivileges);

        // nothing new here
        if ($existingPrivileges === $privileges) {
            return;
        }

        // existing privileges with newly removed privileges applied
        // we can instantly remove them
        $updatedPrivileges = array_intersect($existingPrivileges, $privileges);

        $new = array_diff($privileges, $updatedPrivileges);

        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE `acl_role` SET `privileges` = :privileges, `requested_privileges` = :requestedPrivileges
                WHERE id = (SELECT acl_role_id FROM app WHERE id = :id)
            SQL,
            [
                'privileges' => json_encode($updatedPrivileges, \JSON_THROW_ON_ERROR),
                'requestedPrivileges' => json_encode($new, \JSON_THROW_ON_ERROR),
                'id' => Uuid::fromHexToBytes($appId),
            ]
        );
    }

    /**
     * @param array<string> $privileges
     */
    private function addPrivileges(array $privileges, string $appId, Context $context): void
    {
        $this->connection->executeStatement(
            'UPDATE `acl_role` SET `privileges` = :privileges WHERE id = (SELECT acl_role_id FROM app WHERE id = :id)',
            [
                'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
                'id' => Uuid::fromHexToBytes($appId),
            ]
        );

        $this->eventDispatcher->dispatch(new AppPermissionsUpdated($appId, $privileges, $context));
    }
}
