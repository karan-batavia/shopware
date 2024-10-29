<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Service\Api\ConsentController;
use Shopware\Core\Service\Api\ServiceController;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServicePrivileges;
use Shopware\Core\Service\ServiceRegistryClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ServiceController::class)]
class ConsentControllerTest extends TestCase
{
    private ServicePrivileges&MockObject $servicePrivileges;

    private ServiceRegistryClient&MockObject $serviceRegistryClient;

    private ConsentController $controller;

    protected function setUp(): void
    {
        $this->servicePrivileges = $this->createMock(ServicePrivileges::class);
        $this->serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $this->controller = new ConsentController($this->servicePrivileges, $this->serviceRegistryClient);
    }

    public function testGetConsentWithWrongSource(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Expected context source to be "Shopware\Core\Framework\Api\Context\AdminApiSource" but got "Shopware\Core\Framework\Api\Context\SystemSource"');

        $context = Context::createDefaultContext();
        $this->controller->getConsent($context);
    }

    public function testGetConsentWhenNotLoggedIn(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('No user available in context source "Shopware\Core\Framework\Api\Context\AdminApiSource');

        $context = Context::createDefaultContext(new AdminApiSource(null));
        $this->controller->getConsent($context);
    }

    public function testGetConsent(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->servicePrivileges->expects(static::once())
            ->method('getConsentStatus')
            ->willReturn('pending');

        $this->servicePrivileges->expects(static::once())
            ->method('getPendingPrivileges')
            ->willReturn(['product:read', 'product:update', 'customer:read']);

        $this->serviceRegistryClient->expects(static::once())
            ->method('getDataAgreementUrl')
            ->willReturn('https://services.shopware.com/agreement');

        $response = $this->controller->getConsent($context);

        $content = json_decode((string) $response->getContent(), true);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(
            [
                'status' => 'pending',
                'requestedPermissions' => [
                    'customer' => [
                        [
                            'extensions' => [],
                            'entity' => 'customer',
                            'operation' => 'read',
                        ],
                    ],
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
                'dataAgreementUrl' => 'https://services.shopware.com/agreement',
            ],
            $content
        );
    }

    public function testAcceptConsentWithWrongSource(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Expected context source to be "Shopware\Core\Framework\Api\Context\AdminApiSource" but got "Shopware\Core\Framework\Api\Context\SystemSource"');

        $context = Context::createDefaultContext();
        $this->controller->acceptConsent($context);
    }

    public function testAcceptConsentWhenNotLoggedIn(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('No user available in context source "Shopware\Core\Framework\Api\Context\AdminApiSource');

        $context = Context::createDefaultContext(new AdminApiSource(null));
        $this->controller->acceptConsent($context);
    }

    public function testAcceptConsent(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->servicePrivileges->expects(static::once())
            ->method('accept')
            ->with($context);

        $response = $this->controller->acceptConsent($context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }

    public function testRevokeConsentWithWrongSource(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Expected context source to be "Shopware\Core\Framework\Api\Context\AdminApiSource" but got "Shopware\Core\Framework\Api\Context\SystemSource"');

        $context = Context::createDefaultContext();
        $this->controller->revokeConsent($context);
    }

    public function testRevokeConsentWhenNotLoggedIn(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('No user available in context source "Shopware\Core\Framework\Api\Context\AdminApiSource');

        $context = Context::createDefaultContext(new AdminApiSource(null));
        $this->controller->revokeConsent($context);
    }

    public function testRevokeConsent(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $this->servicePrivileges->expects(static::once())
            ->method('revoke')
            ->with($context);

        $response = $this->controller->revokeConsent($context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }
}
