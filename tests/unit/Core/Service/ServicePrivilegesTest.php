<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Service\ServicePrivileges;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(ServicePrivileges::class)]
class ServicePrivilegesTest extends TestCase
{
    public function testGetConsentStatusReturnsPendingWhenNotSet(): void
    {
        $privileges = new ServicePrivileges(
            new StaticSystemConfigService([]),
            $this->createMock(Connection::class),
            $this->createMock(Privileges::class)
        );

        static::assertSame('pending', $privileges->getConsentStatus());
    }

    public function testGetConsentStatus(): void
    {
        $servicePrivileges = new ServicePrivileges(
            new StaticSystemConfigService(['core.services.dataSharingAgreement' => 'accepted']),
            $this->createMock(Connection::class),
            $this->createMock(Privileges::class)
        );

        static::assertSame('accepted', $servicePrivileges->getConsentStatus());
    }

    public function testCanAcceptPermissionsReturnsTrueWhenConsentIsGiven(): void
    {
        $config = new StaticSystemConfigService(['core.services.dataSharingAgreement' => 'accepted']);
        $servicePrivileges = new ServicePrivileges(
            $config,
            $this->createMock(Connection::class),
            $this->createMock(Privileges::class)
        );

        static::assertTrue($servicePrivileges->canAcceptPermissions());

        $config->set('core.services.dataSharingAgreement', 'declined');

        static::assertFalse($servicePrivileges->canAcceptPermissions());

        $config->set('core.services.dataSharingAgreement', 'pending');

        static::assertFalse($servicePrivileges->canAcceptPermissions());
    }

    public function testAcceptAcceptsForAllServices(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $privileges = $this->createMock(Privileges::class);

        $connection->expects(static::once())
            ->method('fetchFirstColumn')
            ->willReturn(['id1', 'id2']);

        $privileges->expects(static::once())
            ->method('acceptAllForApps')
            ->with(['id1', 'id2'], $context);

        $config = new StaticSystemConfigService(['core.services.dataSharingAgreement' => 'pending']);
        $servicePrivileges = new ServicePrivileges(
            $config,
            $connection,
            $privileges
        );

        $servicePrivileges->accept($context);

        static::assertSame('accepted', $config->get('core.services.dataSharingAgreement'));
    }

    public function testRevokeRevokesForAllServices(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $privileges = $this->createMock(Privileges::class);

        $connection->expects(static::once())
            ->method('fetchFirstColumn')
            ->willReturn(['id1', 'id2']);

        $privileges->expects(static::once())
            ->method('revokeAllForApps')
            ->with(['id1', 'id2'], $context);

        $config = new StaticSystemConfigService(['core.services.dataSharingAgreement' => 'accepted']);
        $servicePrivileges = new ServicePrivileges(
            $config,
            $connection,
            $privileges
        );

        $servicePrivileges->revoke($context);

        static::assertSame('declined', $config->get('core.services.dataSharingAgreement'));
    }

    public function testGetPendingPrivileges(): void
    {
        $connection = $this->createMock(Connection::class);
        $privileges = $this->createMock(Privileges::class);

        $connection->expects(static::once())
            ->method('fetchFirstColumn')
            ->willReturn(['id1', 'id2', 'id3']);

        $servicePrivileges = new ServicePrivileges(
            new StaticSystemConfigService([]),
            $connection,
            $privileges
        );

        $privileges->expects(static::once())
            ->method('getPendingPrivileges')
            ->with(['id1', 'id2', 'id3'])
            ->willReturn(['id1' => ['customer:read', 'customer:write'], 'id2' => ['customer:read', 'product:read'], 'id3' => []]);

        static::assertSame(
            ['customer:read', 'customer:write', 'product:read'],
            $servicePrivileges->getPendingPrivileges()
        );
    }
}
