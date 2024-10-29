<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Permission;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class PrivilegesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private Privileges $privileges;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->privileges = $this->getContainer()->get(Privileges::class);
        $this->appRepository = $this->getContainer()->get('app.repository');
    }

    public function testSetPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['customer:read', 'customer:update'], $context);

        $role = $this->connection->fetchAllAssociative(
            'SELECT privileges, requested_privileges FROM acl_role WHERE name = \'TestApp\''
        );

        static::assertCount(1, $role);

        static::assertSame(
            ['customer:read', 'customer:update'],
            json_decode($role[0]['privileges'], true, \JSON_THROW_ON_ERROR)
        );

        static::assertNull($role[0]['requested_privileges']);
    }

    public function testRequestPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update'], $context);

        $role = $this->connection->fetchAllAssociative(
            'SELECT privileges, requested_privileges FROM acl_role WHERE name = \'TestApp\''
        );

        static::assertCount(1, $role);

        static::assertSame(
            [],
            json_decode($role[0]['privileges'], true, \JSON_THROW_ON_ERROR)
        );

        static::assertSame(
            ['customer:read', 'customer:update'],
            json_decode($role[0]['requested_privileges'], true, \JSON_THROW_ON_ERROR)
        );
    }

    public function testRequestPrivilegesRemovesExistingPrivilegesNotIncludedInRequest(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['customer:read', 'customer:update'], $context);
        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:write'], $context);

        $role = $this->connection->fetchAllAssociative(
            'SELECT privileges, requested_privileges FROM acl_role WHERE name = \'TestApp\''
        );

        static::assertCount(1, $role);

        static::assertSame(
            ['customer:read'],
            json_decode($role[0]['privileges'], true, \JSON_THROW_ON_ERROR)
        );

        static::assertSame(
            ['customer:write'],
            json_decode($role[0]['requested_privileges'], true, \JSON_THROW_ON_ERROR)
        );
    }

    public function testRequestSamePrivilegesAsExisting(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['product:read', 'product:update'], $context);
        $this->privileges->requestPrivileges($appId, ['product:read', 'product:update'], $context);

        $role = $this->connection->fetchAllAssociative(
            'SELECT privileges, requested_privileges FROM acl_role WHERE name = \'TestApp\''
        );

        static::assertCount(1, $role);

        static::assertSame(
            ['product:read', 'product:update'],
            json_decode($role[0]['privileges'], true, \JSON_THROW_ON_ERROR)
        );

        static::assertNull($role[0]['requested_privileges']);
    }

    public function testRevokeAllPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['product:read', 'product:update'], $context);
        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update', 'product:read', 'product:update'], $context);

        $role = $this->connection->fetchAllAssociative(
            'SELECT privileges, requested_privileges FROM acl_role WHERE name = \'TestApp\''
        );

        static::assertCount(1, $role);

        static::assertSame(
            ['product:read', 'product:update'],
            json_decode($role[0]['privileges'], true, \JSON_THROW_ON_ERROR)
        );

        static::assertSame(
            ['customer:read', 'customer:update'],
            json_decode($role[0]['requested_privileges'], true, \JSON_THROW_ON_ERROR)
        );

        $this->privileges->revokeAllForApps([$appId], $context);

        $role = $this->connection->fetchAllAssociative(
            'SELECT privileges, requested_privileges FROM acl_role WHERE name = \'TestApp\''
        );

        static::assertCount(1, $role);

        static::assertSame([], json_decode($role[0]['privileges'], true, \JSON_THROW_ON_ERROR));
        static::assertSame([], json_decode($role[0]['requested_privileges'], true, \JSON_THROW_ON_ERROR));
    }

    public function testAcceptAllPrivilegesAcceptsRequestedPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update'], $context);

        $role = $this->connection->fetchAllAssociative(
            'SELECT privileges, requested_privileges FROM acl_role WHERE name = \'TestApp\''
        );

        static::assertCount(1, $role);

        static::assertSame(
            [],
            json_decode($role[0]['privileges'], true, \JSON_THROW_ON_ERROR)
        );

        static::assertSame(
            ['customer:read', 'customer:update'],
            json_decode($role[0]['requested_privileges'], true, \JSON_THROW_ON_ERROR)
        );

        $this->privileges->acceptAllForApps([$appId], $context);

        $role = $this->connection->fetchAllAssociative(
            'SELECT privileges, requested_privileges FROM acl_role WHERE name = \'TestApp\''
        );

        static::assertCount(1, $role);
        static::assertSame([], json_decode($role[0]['requested_privileges'], true, \JSON_THROW_ON_ERROR));
        static::assertSame(
            ['customer:read', 'customer:update'],
            json_decode($role[0]['privileges'], true, \JSON_THROW_ON_ERROR)
        );
    }

    public function testAcceptOnlyPrivilegesAcceptsSpecifiedRequestedPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update'], $context);

        $role = $this->connection->fetchAllAssociative(
            'SELECT privileges, requested_privileges FROM acl_role WHERE name = \'TestApp\''
        );

        static::assertCount(1, $role);

        static::assertSame(
            [],
            json_decode($role[0]['privileges'], true, \JSON_THROW_ON_ERROR)
        );

        static::assertSame(
            ['customer:read', 'customer:update'],
            json_decode($role[0]['requested_privileges'], true, \JSON_THROW_ON_ERROR)
        );

        $this->privileges->acceptOnly($appId, ['customer:update'], $context);

        $role = $this->connection->fetchAllAssociative(
            'SELECT privileges, requested_privileges FROM acl_role WHERE name = \'TestApp\''
        );

        static::assertCount(1, $role);
        static::assertSame(['customer:read'], json_decode($role[0]['requested_privileges'], true, \JSON_THROW_ON_ERROR));
        static::assertSame(
            ['customer:update'],
            json_decode($role[0]['privileges'], true, \JSON_THROW_ON_ERROR)
        );
    }

    public function testGetPendingPrivilegesSingleApp(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update'], $context);

        static::assertSame(
            [
                $appId => ['customer:read', 'customer:update'],
            ],
            $this->privileges->getPendingPrivileges([$appId])
        );
    }

    public function testGetPendingPrivilegesMultiApp(): void
    {
        $appId1 = $this->createApp();
        $appId2 = $this->createApp('App2');
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId1, ['customer:read', 'customer:update'], $context);
        $this->privileges->requestPrivileges($appId2, ['product:read', 'product:update'], $context);

        static::assertSame(
            [
                $appId1 => ['customer:read', 'customer:update'],
                $appId2 => ['product:read', 'product:update'],
            ],
            $this->privileges->getPendingPrivileges([$appId1, $appId2])
        );
    }

    public function testGetPendingPrivilegesForAllApps(): void
    {
        $appId1 = $this->createApp();
        $appId2 = $this->createApp('App2');
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId1, ['customer:read', 'customer:update'], $context);
        $this->privileges->requestPrivileges($appId2, ['product:read', 'product:update'], $context);

        static::assertSame(
            [
                $appId1 => ['customer:read', 'customer:update'],
                $appId2 => ['product:read', 'product:update'],
            ],
            $this->privileges->getPendingPrivilegesForAllApps()
        );
    }

    private function createApp(string $name = 'TestApp'): string
    {
        $id = Uuid::randomHex();
        $app = [
            'id' => $id,
            'name' => $name,
            'active' => true,
            'path' => __DIR__,
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => $name,
            ],
        ];

        $this->appRepository->create([$app], Context::createDefaultContext());

        return $id;
    }
}
