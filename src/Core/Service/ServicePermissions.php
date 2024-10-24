<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('core')]
class ServicePermissions
{
    private const PENDING = 0;
    private const ACCEPTED = 1;
    private const DECLINED = 2;

    private const CONFIG_KEY = 'core.services.dataSharingAgreement';

    public function __construct(
        private readonly SystemConfigService $config,
        private readonly Connection $connection,
        private readonly PermissionPersister $permissionPersister
    ) {
    }

    public function accept(Context $context): void
    {
        $this->config->set(self::CONFIG_KEY, self::ACCEPTED);

        /** @var list<string> $appIds */
        $appIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM app WHERE self_managed = 1'
        );

        foreach ($appIds as $appId) {
            $this->permissionPersister->acceptPrivileges($appId, $context);
        }
    }

    public function decline(): void
    {
        $this->config->set(self::CONFIG_KEY, self::DECLINED);
    }

    public function canAcceptPermissions(): bool
    {
        return $this->config->getInt(self::CONFIG_KEY) === self::ACCEPTED;
    }

    public function getPendingPermissions(): array
    {
        $privileges = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT requested_privileges
                FROM `acl_role`
                WHERE id IN (
                    SELECT acl_role_id FROM app WHERE self_managed = 1
                )
            SQL
        );

        dd($privileges);
    }
}
