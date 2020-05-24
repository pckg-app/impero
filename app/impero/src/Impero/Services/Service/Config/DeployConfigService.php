<?php namespace Impero\Services\Service\Config;

use Exception;
use Impero\Apache\Record\Site;
use Impero\Apache\Record\SitesServer;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Impero\Services\Service\Helper\SiteAndServer;
use Pckg\Framework\Console\Command;

class DeployConfigService extends Command
{

    use SiteAndServer;

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('service:config:deploy')
             ->setDescription('Activa te apache for site on server')
             ->addSiteAndServerOptions();
    }

    /**
     * @throws Exception
     */
    public function handle()
    {
        $this->storeOptions();
return;
        $task = Task::create('Copying config for site #' . $this->site->id . ' on server #' . $this->server->id);

        $task->make(function() {
            $this->deployConfigToServer();
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
    public function deployConfigToServer()
    {
        $site = $this->site;
        $server = $this->server;
        $config = $this->config;

        $connection = $server->getConnection();
        $pckg = $site->getImperoPckgAttribute();
        SitesServer::getOrCreate(['type' => 'config', 'site_id' => $site->id, 'server_id' => $server->id]);

        foreach ($pckg['checkout']['config'] ?? [] as $dest => $copy) {
            $destination = $site->getHtdocsPath() . $dest;
            if ($connection->fileExists($destination)) {
                $connection->deleteFile($destination);
            }
            /**
             * The issue with config file is that variable values may be different between checkouts:
             *  - db server: 127.0.0.1 / 10.8.0.1 / 10.135.61.34
             *  - job parameter: --type = newsletter / transactional
             * MySQL service should be smart enough to automatically resolve routing IP.
             * Job parameter will be requested in web interface
             * Either way, special settings are saved under SitesServer ?
             */
            $configContent = $site->getConfigFileContent($connection, $copy);
            $connection->saveContent($destination, $configContent);
        }
    }

}