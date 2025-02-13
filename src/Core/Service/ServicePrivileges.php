<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
class ServicePrivileges
{
    private const PENDING = 'pending';
    private const ACCEPTED = 'accepted';
    private const DECLINED = 'declined';

    private const CONFIG_KEY = 'core.services.dataSharingAgreement';

    public function __construct(
        private readonly SystemConfigService $config,
        private readonly Connection $connection,
        private readonly Privileges $privileges,
    ) {
    }

    public function getConsentStatus(): string
    {
        /** @var string|null $status */
        $status = $this->config->get(self::CONFIG_KEY);

        if ($status === null) {
            return self::PENDING;
        }

        return $status;
    }

    public function accept(Context $context): void
    {
        $this->config->set(self::CONFIG_KEY, self::ACCEPTED);

        /** @var list<string> $serviceIds */
        $serviceIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM app WHERE self_managed = 1'
        );

        $this->privileges->acceptAllForApps($serviceIds, $context);
    }

    public function revoke(Context $context): void
    {
        $this->config->set(self::CONFIG_KEY, self::DECLINED);

        /** @var list<string> $serviceIds */
        $serviceIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM app WHERE self_managed = 1'
        );

        $this->privileges->revokeAllForApps($serviceIds, $context);
    }

    public function canAcceptPermissions(): bool
    {
        return $this->config->getString(self::CONFIG_KEY) === self::ACCEPTED;
    }

    /**
     * @return array<string>
     */
    public function getPendingPrivileges(): array
    {
        $serviceIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM app WHERE self_managed = 1'
        );

        return array_values(
            array_unique(
                array_merge(
                    ...array_values($this->privileges->getPendingPrivileges($serviceIds))
                )
            )
        );
    }
}
