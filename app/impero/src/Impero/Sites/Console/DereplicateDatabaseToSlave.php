<?php namespace Impero\Sites\Console;

use Impero\Apache\Entity\Sites;
use Impero\Apache\Entity\SitesServers;
use Impero\Apache\Record\Site;
use Impero\Servers\Record\Server;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class DereplicateDatabaseToSlave extends Command
{

    public function configure()
    {
        $this->setName('site:database:dereplicate-from-slave')
             ->setDescription('Scale site database from slave')
             ->addOptions([
                              'site'    => 'Site ID',
                              'from'    => 'Server ID',
                              'project' => 'Project ID',
                          ], InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        $fromOption = $this->option('from');
        $siteOption = $this->option('site');

        if (!$fromOption) {
            throw new \Exception('From server is required');
        }

        $from = Server::getOrFail($fromOption);

        $sites = (new Sites())->where('id', (new SitesServers())->select('site_id')->where('server_id', 2))
                              ->where('id', (new SitesServers())->select('site_id')->where('server_id', $from->id));
        if ($siteOption) {
            $sites->where('id', $siteOption);
        }
        $sites = $sites->all();

        $sites->each(function(Site $site) use ($from, $siteOption) {
            if (!$siteOption && !$this->askConfirmation('Do you want to dereplicate ' . $site->server_name . '?')) {
                return;
            }
            $this->outputDated('Dereplicating site ' . $site->server_name . ' databases from ' . $from->name);
            $site->dereplicateDatabasesFromSlave($from);
            $this->outputDated('Dereplicated');
        });
    }

}