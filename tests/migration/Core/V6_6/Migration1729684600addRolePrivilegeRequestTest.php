<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\ColumnExistsTrait;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1729684600addRolePrivilegeRequest;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1729684600addRolePrivilegeRequest::class)]
class Migration1729684600addRolePrivilegeRequestTest extends TestCase
{
    use ColumnExistsTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement(
                'ALTER TABLE `acl_role` DROP COLUMN `requested_privileges`;'
            );
        } catch (\Throwable) {
        }
    }

    public function testMigration(): void
    {
        static::assertFalse($this->columnExists($this->connection, 'acl_role', 'requested_privileges'));

        $migration = new Migration1729684600addRolePrivilegeRequest();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->columnExists($this->connection, 'acl_role', 'requested_privileges'));
    }
}
