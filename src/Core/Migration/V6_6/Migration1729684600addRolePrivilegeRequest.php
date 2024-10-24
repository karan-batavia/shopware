<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1729684600addRolePrivilegeRequest extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1729684600;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'acl_role',
            'requested_privileges',
            'json',
        );
    }
}
