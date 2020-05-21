<?php namespace Impero\Servers\Console;

use Impero\Servers\Record\Server;
use Impero\Services\Service\Ssh;
use Impero\Services\Service\Ufw;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class ProtectServer extends Command
{

    protected function configure()
    {
        $this->setName('server:protect')
            ->setDescription('Apply secutiry layer')
            ->addOptions([
                'server' => 'Server name',
            ], InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        $server = Server::getOrFail($this->option('server'));

        /**
         * Install UFW, allow only HTTP and SSH traffic.
         */
        (new Ufw($server->getConnection()))->activate();
    }

}