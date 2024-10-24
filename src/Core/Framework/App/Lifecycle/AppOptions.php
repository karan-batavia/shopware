<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class AppOptions
{
    public function __construct(
        public readonly bool $activate = true,
        public readonly bool $acceptPermissions = true
    ) {
    }
}
