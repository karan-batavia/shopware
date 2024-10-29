<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\AppOptionsUpdate;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(AppOptionsUpdate::class)]
class AppOptionsUpdateTest extends TestCase
{
    public function testAccessors(): void
    {
        $options = new AppOptionsUpdate();

        static::assertTrue($options->acceptPermissions);

        $options = new AppOptionsUpdate(false);

        static::assertFalse($options->acceptPermissions);
    }
}
