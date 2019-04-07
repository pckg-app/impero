<?php namespace Impero\Services\Service\Apache;

use Exception;
use Impero\Apache\Record\Site;
use Impero\Apache\Record\SitesServer;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Impero\Services\Service\Helper\SiteAndServer;
use Pckg\Framework\Console\Command;

/**
 * Class DeployApacheService
 *
 * @package Impero\Services\Service\Apache
 */
class DeployApacheService extends Command
{

    use SiteAndServer;

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('service:apache:deploy')
             ->setDescription('Activate apache for site on server')
             ->addSiteAndServerOptions();
    }

    /**
     * @throws Exception
     */
    public function handle()
    {
        $this->storeOptions();

        $task = Task::create('Deploying apache service for site #' . $this->site->id . ' on server #' .
                             $this->server->id);

        $task->make(function() {
            $this->deploySiteToServer();
        }, function(Task $task, \Throwable $e) {
            $this->emitErrorEvent();
            throw $e;
        });

        $this->emitFinalEvent();
    }

    /**
     * @param Site   $site
     * @param Server $server
     *
     * @throws Exception
     */
    public function deploySiteToServer()
    {
        $site = $this->site;
        $server = $this->server;
        $config = $this->config;

        /**
         * Link server and site's service.
         */
        SitesServer::getOrCreate(['server_id' => $server->id, 'site_id' => $site->id, 'type' => 'web']);

        /**
         * Web service, log service and https service.
         * Impero is web management service, so we create those by default.
         * However, server_id is only primary server, services may be expanded to other servers.
         *
         * @T00D00 - collect all service servers and initial configuration:
         *         - is loadbalanced? (default: no)
         *         - web workers? (default: 1; additional: x)
         *         - mysql master and slave configuration (default: master only; additional: master-slave)
         *         - storages (default: root; additional: volume)
         *         Impero should know which services live on which server and how is network connected.
         *         We need to know about entrypoint (floating ip, server)
         */
        $site->createOnFilesystem($server);

        /**
         * Notify listeners that filesystem is ready.
         */
        $this->emitEvent('filesystem:ready');

        /**
         * Enable https on website.
         * This will call letsencrypt and ask for new certificate.
         * It will also add ssl virtualhost and restart apache.
         */
        $site->redeploySslService();
    }

}