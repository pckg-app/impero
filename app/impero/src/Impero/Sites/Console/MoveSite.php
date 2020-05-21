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

class MoveSite extends Command
{

    protected function configure()
    {
        $this->setName('site:move')
            ->setDescription('Move site to the new server')
            ->addOptions([
                'server' => 'Server ID or name',
                'site' => 'Site',
            ], InputOption::VALUE_REQUIRED)
            ->addOptions([
                'no-backup' => 'No backup',
                'no-restore' => 'No restore',
            ], InputOption::VALUE_NONE);
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

        //$site->removeFromWeb();

        /**
         * Then we can remove cron services.
         */
        //$site->removeFromCron();

        /**
         * Services are not active, so we can migrate them.
         * Let's create backups first.
         */
        if (!$this->option('no-backup')) {
            //$site->createFullBackup();
        }

        /**
         * Now we can download them on the target server.
         * But first, let's prepare the checkout there.
         * This is NOT standard CHECKOUT procedure NOR standard RECHECKOUT procedure. It is NEW MOVECHECKOUT procedure.
         */
        $site->moveCheckout($server);
    }

}