<?php namespace Impero\Services\Service\Backup\Console;

use Impero\Apache\Entity\Sites;
use Impero\Apache\Entity\SitesServers;
use Impero\Apache\Record\Site;
use Impero\Mysql\Record\Database;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Impero\Services\Service\Backup;
use Impero\Services\Service\Docker;
use Impero\Services\Service\Mysql;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\Cron\Fork;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Class RestoreSiteBackup
 *
 * @package Impero\Services\Service\Backup\Console
 */
class RestoreSiteBackup extends Command
{

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('service:backup:site:restore')
            ->setDescription('Restore cold backup of databases, storage, SSL and configuration')
            ->addOptions([
                '--site' => 'Site name or ID',
                '--server' => 'Server',
            ], InputOption::VALUE_REQUIRED);
    }

    /**
     *
     */
    public function handle()
    {
        $site = Site::getOrFail($this->option('site'));
        $server = Server::getOrFail($this->option('server'));

        // $databaseSecret = $site->getLastSecretFor('mysql:dump');
        $storageSecret = $site->getLastSecretFor('storage:dump');
        $sslSecret = $site->getLastSecretFor('ssl:dump');

        /**
         * Now let's restore each of them.
         * MySQL is by default restored to the droplet.
         * For Medium we'll restore it to container for first time.
         * This means we have server #1 foobar, #10 medium on which we have docker with service / container.
         * We will pass connection to the database container.
         */
        if (false) {
        Backup::fullColdRestore(new Backup($server), $databaseSecret, function ($file) use ($server, $site) {
            /**
             * When in swarm, we want to connect to the database to the container, and have container's shell.
             * Since this is not possible we need to wrap things up.
             */
            $swarmName = $site->getImperoPckgAttribute()['checkout']['swarm']['name'] ?? null;
            if ($swarmName) {
                /**
                 * This is simply a wrapper for "docker exec -it ..." and "docker exec -it mysql -u root -p ... -e \"\""
                 * Will it work for all cases?
                 * I think not, let's simply output the instructions.
                 */
                d('Manually import ' . $file . ' to Docker container');
                return;
                $mysqlContainerConnection = (new Docker($server))->getContainerConnection($swarmName, 'database');
                $mysql = $mysqlContainerConnection;
            } else {
                $mysql = (new Mysql($server));
            }
            // how to get database name?
            $name = null;
            $mysql->restoreDatabase($name, $file);
        }, $server);
        }

        /**
         * Make sure that htdocs directory exists.
         */
        $site->createOnFilesystem($server);

        $this->outputDated('Restoring storage');
        $backupService = new Backup($server);
        Backup::fullColdRestore($backupService, $storageSecret, function ($dir) use ($backupService, $site) {
            /**
             * We extracted files to /home/impero/impero/random/somedirectory.
             * We would like to move it to ./htdocs/storage/
             */
            $backupService->importStorageBackup($dir . $site->getHtdocsPath() . 'storage', $site->getHtdocsPath() . 'storage');
            // restore to ./htdocs/storage/ ?
        }, $server, Backup::TYPE_DIR);
        $this->outputDated('Storage restored');

        $this->outputDated('Restoring SSL');
        Backup::fullColdRestore(new Backup($server), $sslSecret, function ($dir) use ($backupService, $site) {
            $backupService->importSslBackup($dir, $site->getSslDomain());
            // restore to ./etc/letsencrypt/ ?
        }, $server, Backup::TYPE_DIR);
        $this->outputDated('SSL restored');
    }

}