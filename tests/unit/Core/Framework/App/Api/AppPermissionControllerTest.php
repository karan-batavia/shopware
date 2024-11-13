<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\Api\AppPermissionController;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AppPermissionController::class)]
class AppPermissionControllerTest extends TestCase
{
    private AppPermissionController $controller;

    private Connection&MockObject $connection;

    private Privileges&MockObject $privileges;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->privileges = $this->createMock(Privileges::class);
        $this->controller = new AppPermissionController($this->connection, $this->privileges);
    }

    public function testGetRequestedPermissionsWithWrongSource(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Expected context source to be "Shopware\Core\Framework\Api\Context\AdminApiSource" but got "Shopware\Core\Framework\Api\Context\SystemSource"');

        $context = Context::createDefaultContext();
        $this->controller->getRequestedPermissions($context);
    }

    public function testGetRequestedPermissionsWhenNotLoggedIn(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('No user available in context source "Shopware\Core\Framework\Api\Context\AdminApiSource');

        $context = Context::createDefaultContext(new AdminApiSource(null));
        $this->controller->getRequestedPermissions($context);
    }

    public function testGetRequestedPermissions(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->privileges->expects(static::once())
            ->method('getPendingPrivilegesForAllApps')
            ->with()
            ->willReturn([
                'App1' => ['customer:read', 'customer:update'],
                'App2' => ['product:read', 'product:update'],
            ]);

        $response = $this->controller->getRequestedPermissions($context);

        $content = json_decode((string) $response->getContent(), true);

        static::assertSame(
            [
                'requestedPermissions' => [
                    'App1' => [
                        'customer' => [
                            [
                                'extensions' => [],
                                'entity' => 'customer',
                                'operation' => 'read',
                            ],
                            [
                                'extensions' => [],
                                'entity' => 'customer',
                                'operation' => 'update',
                            ],
                        ],
                    ],
                    'App2' => [
                        'product' => [
                            [
                                'extensions' => [],
                                'entity' => 'product',
                                'operation' => 'read',
                            ],
                            [
                                'extensions' => [],
                                'entity' => 'product',
                                'operation' => 'update',
                            ],
                        ],
                    ],
                ], ],
            $content
        );
    }

    public function testAcceptPermissionsWithWrongSource(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Expected context source to be "Shopware\Core\Framework\Api\Context\AdminApiSource" but got "Shopware\Core\Framework\Api\Context\SystemSource"');

        $context = Context::createDefaultContext();

        $request = new Request();
        $this->controller->acceptPermissions($request, $context, 'app-id-1');
    }

    public function testAcceptPermissionsWhenNotLoggedIn(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('No user available in context source "Shopware\Core\Framework\Api\Context\AdminApiSource');

        $context = Context::createDefaultContext(new AdminApiSource(null));

        $request = new Request();
        $this->controller->acceptPermissions($request, $context, 'app-id-1');
    }

    public function testAcceptPermissionsWithEmptyRequest(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->privileges->expects(static::never())->method('acceptOnly');

        $request = new Request(content: (string) json_encode([]));

        static::expectException(AppException::class);
        static::expectExceptionMessage('Expected a list of permissions in the format "category:read"');

        $response = $this->controller->acceptPermissions($request, $context, 'app-id-1');

        static::assertSame(204, $response->getStatusCode());
    }

    public function testAcceptPermissionsWithMalformedRequest(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->privileges->expects(static::never())->method('acceptOnly');

        $request = new Request(content: (string) json_encode(['id1', 'not-id' => []]));

        static::expectException(AppException::class);
        static::expectExceptionMessage('Expected a list of permissions in the format "category:read"');

        $response = $this->controller->acceptPermissions($request, $context, 'app-id-1');

        static::assertSame(204, $response->getStatusCode());
    }

    public function testAcceptPermissionsWithNonExistentAppName(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->with('SELECT LOWER(HEX(id)) FROM app WHERE name = ?', ['appName'])
            ->willReturn(false);

        $this->privileges->expects(static::never())->method('acceptOnly');

        static::expectException(AppException::class);
        static::expectExceptionMessage('Could not find app with name "appName"');

        $request = new Request(content: (string) json_encode(['customer:read', 'customer:update']));
        $response = $this->controller->acceptPermissions($request, $context, 'appName');

        static::assertSame(204, $response->getStatusCode());
    }

    public function testAcceptPermissions(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->with('SELECT LOWER(HEX(id)) FROM app WHERE name = ?', ['appName'])
            ->willReturn('app-id-1');

        $this->privileges->expects(static::once())
            ->method('acceptOnly')
            ->with('app-id-1', ['customer:read', 'customer:update'], $context);

        $request = new Request(content: (string) json_encode(['customer:read', 'customer:update']));
        $response = $this->controller->acceptPermissions($request, $context, 'appName');

        static::assertSame(204, $response->getStatusCode());
    }
}
