<?php declare(strict_types=1);

namespace Shopware\Core\Service\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServicePermissions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Package('core')]
#[AsCommand(
    name: 'services:accept-permissions',
    description: 'Accept permissions'
)]
class AcceptPermissions extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly ServicePermissions $servicePermissions)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $io->title('Accepting service permissions');

        $this->servicePermissions->accept(Context::createCLIContext());

        return Command::SUCCESS;
    }
}
