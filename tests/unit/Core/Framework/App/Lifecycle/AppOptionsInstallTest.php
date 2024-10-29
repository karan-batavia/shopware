<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\AppOptionsInstall;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(AppOptionsInstall::class)]
class AppOptionsInstallTest extends TestCase
{
    public function testAccessors(): void
    {
        $options = new AppOptionsInstall();

        static::assertTrue($options->activate);
        static::assertTrue($options->acceptPermissions);

        $options = new AppOptionsInstall(false, false);

        static::assertFalse($options->activate);
        static::assertFalse($options->acceptPermissions);
    }
}
