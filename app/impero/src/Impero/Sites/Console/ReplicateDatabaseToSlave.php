<?php namespace Impero\Sites\Console;

use Impero\Apache\Entity\Sites;
use Impero\Apache\Entity\SitesServers;
use Impero\Apache\Record\Site;
use Impero\Servers\Record\Server;
use Impero\Services\Service\Mysql;
use Pckg\Database\Query;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class ReplicateDatabaseToSlave extends Command
{

    public function configure()
    {
        $this->setName('site:database:replicate-to-slave')->setDescription('Scale site database to slave')->addOptions([
                                                                                                                           'site'    => 'Site ID',
                                                                                                                           'from'    => 'Server ID',
                                                                                                                           'to'      => 'Server ID',
                                                                                                                           'project' => 'Project ID',
                                                                                                                       ],
                                                                                                                       InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        $siteOption = $this->option('site');
        $fromOption = $this->option('from');
        $toOption = $this->option('to');
        $projectOption = $this->option('project');

        // $from = Server::getOrFail($fromOption);
        $to = Server::getOrFail($toOption);

        $sites = (new Sites())->where('id', (new SitesServers())->select('site_id')->where('server_id', 2))
                              ->where('id', (new SitesServers())->select('site_id')->where('server_id', 4),
                                      Query::NOT_IN)
                              ->where('id', $siteOption)
                              ->all();

        /*$mysqlSlaveService = (new Mysql($to->getConnection()));
        $mysqlSlaveService->stopSlave();
        $databasesOnSlave = $to->slaveDatabases();
        $mysqlSlaveService->refreshSlaveReplicationFilter($databasesOnSlave);
        $mysqlSlaveService->dumpSlaveReplicationFilter($databasesOnSlave);
        $mysqlSlaveService->startSlave();
        dd('ok');*/

        $sites->each(function(Site $site) use ($to, $siteOption) {
            if (!$siteOption && !$this->askConfirmation('Do you want to replicate ' . $site->server_name . '?')) {
                return;
            }
            $this->outputDated('Replicating site ' . $site->server_name . ' databases to ' . $to->name);
            $site->replicateDatabasesToSlave($to);
            $this->outputDated('Replicated');
        });
    }

}