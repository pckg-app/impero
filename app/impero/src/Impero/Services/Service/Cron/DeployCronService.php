<?php namespace Impero\Services\Service\Cron;

use Exception;
use Impero\Apache\Record\Site;
use Impero\Apache\Record\SitesServer;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Impero\Services\Service\Helper\SiteAndServer;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class DeployCronService
 *
 * @package Impero\Services\Service\Cron
 */
class DeployCronService extends Command
{

    use SiteAndServer;

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('service:cron:deploy')
             ->setDescription('Activa te apache for site on server')
             ->addSiteAndServerOptions();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function handle()
    {
        $this->storeOptions();

        $task = Task::create('Enabling cronjobs for site #' . $this->site->id);

        $task->make(function() {
            $this->deployCronToServer();
        }, function(Task $task, \Throwable $e) {
            $this->emitErrorEvent();
            throw $e;
        });

        $this->emitFinalEvent();
    }

    /**
     * @param Site   $site
     * @param Server $server
     */
    public function deployCronToServer()
    {
        $site = $this->site;
        $server = $this->server;
        $config = $this->config;

        /**
         * Link cron service to site and server.
         */
        SitesServer::getOrCreate([
                                     'site_id'   => $site->id,
                                     'server_id' => $server->id,
                                     'type'      => 'cron',
                                 ]);

        /**
         * Add cronjob, we also perform uniqueness check.
         */
        foreach ($config['commands'] ?? [] as $cron) {
            $command = $site->replaceVars($cron['command']);
            $server->addCronjob($command);
        }
    }

}