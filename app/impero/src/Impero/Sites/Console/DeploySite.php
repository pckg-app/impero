<?php namespace Impero\Sites\Console;

use Impero\Apache\Entity\Sites;
use Impero\Apache\Entity\SitesServers;
use Impero\Apache\Record\Site;
use Impero\Apache\Record\SitesServer;
use Impero\Mysql\Entity\Databases;
use Impero\Servers\Record\Server;
use Impero\Services\Service\Backup;
use Impero\Storage\Entity\Storages;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class DeploySite extends Command
{

    protected function configure()
    {
        $this->setName('site:deploy')
            ->setDescription('Deploy site on server (as webhook would)')
            ->addOptions([
                'server' => 'Server ID or name',
                'site' => 'Site',
            ], InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        /**
         * We will remove the site from configuration of all known web services first: haproxy, apache, nginx, ...
         */
        $site = Site::getOrFail($this->option('site'));
        $server = Server::getOrFail($this->option('server'));
        $webs = (new SitesServers())->whereArr(['site_id' => $site->id, 'type' => 'web'])->all();

        if (!$webs->count()) {
            $this->outputDated('No webs');
            return;
        }

        if (!$this->askConfirmation('Site ' . $site->server_name . '?')) {
            return;
        }

        $site->deploy($server, true, true, true);
    }

}