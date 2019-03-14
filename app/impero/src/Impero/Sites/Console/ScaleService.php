<?php namespace Impero\Sites\Console;

use Impero\Apache\Record\Site;
use Impero\Servers\Record\Server;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class ScaleService extends Command
{

    public function configure()
    {
        $this->setName('service:scale')->setDescription('Deploy services to server for site')->addOptions([
                                                                                                           'server'  => 'Server ID',
                                                                                                           'site'    => 'Site ID',
                                                                                                           'service' => 'Service',
                                                                                                       ],
                                                                                                       InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        $site = Site::getOrFail($this->option('site'));
        $server = Server::getOrFail($this->option('server'));
        $service = $this->option('service');

        if (!$site || !$server || !$service) {
            $this->outputDated('Site, server and service parameters are required, exiting');

            return;
        }

        if ($service == 'web') {
            $this->outputDated('Adding web worker for site #' . $site->id . ' on server #' . $server->id);

            $site->addWebWorker($server);
        }
    }

}