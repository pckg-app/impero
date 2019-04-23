<?php namespace Impero\Services\Service\Storage\Console;

use Exception;
use Impero\Apache\Record\Site;
use Impero\Apache\Record\SitesServer;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Impero\Services\Service\Helper\SiteAndServer;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class DeployStorageResource
 *
 * @package Impero\Services\Service\Storage
 */
class DeployStorageResource extends Command
{

    use SiteAndServer;

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('resource:storage:deploy')
             ->setDescription('Activa te apache for site on server')
             ->addSiteAndServerOptions();
    }

    /**
     * @throws Exception
     */
    public function handle()
    {
        $this->storeOptions();

        $task = Task::create('Preparing site #' . $this->site->id . ' directories on server #' . $this->server->id);

        $task->make(function() {
            $this->deployStorageToServer();
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
    public function deployStorageToServer()
    {
        $this->createDirectories();

        $this->mountWebServices();
    }

    protected function createDirectories()
    {
        $siteStoragePath = $this->site->getStorageDir();
        $htdocsOldPath = $this->site->getHtdocsOldPath();
        $connection = $this->server->getConnection();

        /**
         * Create site dir.
         */
        $hasSiteDir = $connection->dirExists($siteStoragePath);
        if (!$hasSiteDir) {
            $connection->makeAndAllow($siteStoragePath);
        }

        $config = $this->getSitePckgConfig();
        foreach ($config['dir'] ?? [] as $storageDir) {
            $hasStorageDir = $connection->dirExists($siteStoragePath . $storageDir);
            if ($hasStorageDir) {
                /**
                 * Storage already exists and will be mounted.
                 */
                continue;
            }

            $hasOldDir = $connection->dirExists($htdocsOldPath . $storageDir);
            if (!$hasOldDir) {
                /**
                 * Create $storageDir directory in site's directory on storage server.
                 */
                $connection->makeAndAllow($siteStoragePath . $storageDir);
                continue;
            }
            /**
             * Existing dirs are copied to storage server.
             * Recreation is skipped.
             */
            $connection->makeAndAllow($siteStoragePath . $storageDir);

            /**
             * Transfer contents.
             * This was only needed before we've mounted all storage directories?
             *
             * @T00D00 - check if this is needed at all times?
             */
            /*$connection->exec('rsync -a ' . $htdocsOldPath . $storageDir . '/ ' . $siteStoragePath . $storageDir .
                              '/ --stats');*/
        }
    }

    protected function mountWebServices()
    {
        /**
         * Create symlink.
         * Also, check if dir exists
         *
         * @T00D00 - check if this should be moved before previous loop?
         */
        $pckg = $this->site->getImperoPckgAttribute();
        foreach ($pckg['services']['web']['mount'] ?? [] as $linkPoint => $storageDir) {
            /**
             * If mount point was previously created, it will be recreated.
             * If it was directory and it does
             */
            $originPoint = $this->site->replaceVars($storageDir);
            $fullLinkPoint = $this->site->getHtdocsPath() . $linkPoint;
            $this->server->getConnection()->exec('ln -s ' . $originPoint . ' ' . $fullLinkPoint);
        }
    }

}