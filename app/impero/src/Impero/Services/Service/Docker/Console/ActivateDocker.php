<?php namespace Impero\Services\Service\Docker\Console;

use Impero\Servers\Record\Server;
use Impero\Services\Service\Docker;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class ActivateDocker extends Command
{

    protected function configure()
    {
        $this->setName('service:docker:activate')
            ->setDescription('Install Docker and activate Swarm')
            ->addOptions([
                'server' => 'Server ID or name',
            ], InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        $server = Server::getOrFail($this->option('server'));

        $this->output('Activating Docker');

        $docker = (new Docker($server->getConnection()));
        $docker->activate();

        $this->output('Docker activated, initializing Swarm');

        /**
         * Initialize swarm and publish it on the private ip.
         */
        $docker->initSwarm($server->getPrivateIpAttribute());

        $this->outputDated('Swarm initialized');
    }

}