<?php namespace Impero\Services\Service\Backup\Console;

use Exception;
use Impero\Mysql\Entity\Databases;
use Impero\Mysql\Record\Database;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\Cron\Fork;
use Rollbar\Payload\Data;
use Throwable;

/**
 * Class MakeMysqlBackup
 *
 * @package Impero\Services\Service\Backup\Console
 */
class MakeMysqlBackup extends Command
{

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('service:mysql:backup')
             ->setDescription('Make cold backup of mysql databases and live binlog sync');
    }

    /**
     *
     */
    public function handle()
    {
        /**
         * Connect to all different databases on servers and make a backup.
         *
         * @T00D00 ... make backup from slaves whenever possible
         *         ... also make a backup of binlogs
         */
        $servers = (new Servers())->where('id', 2)->withSites()->all();

        /**
         * Make "cold" backup - transactional mysql dump.
         * We can process each server simultaniosly.
         * For starters, this should be enough.
         * As soon as possible we have to implement fully automated restore strategy.
         * Also, filter only master servers.
         */
        $servers->each(
            function(Server $server) {
                try {
                    /*$pid = Fork::fork(
                        function() use ($server) {*/
                            $this->outputDated('Started #' . $server->id . ' cold backup');

                            /**
                             * Make backup of each database separately.
                             */
                            $server->masterDatabases->each(
                                function(Database $database) {
                                    $this->outputDated('Starting ' . $database->name);
                                    $database->backup();
                                    $this->outputDated('Finished ' . $database->name);
                                }
                            );
                            $this->outputDated('Ended #' . $server->id . ' cold backup');
                        /*},
                        function() use ($server) {
                            return 'impero:backup:mysql:' . $server->id;
                        },
                        function() {
                            throw new Exception('Cannot run mysql backup in parallel');
                        }
                    );
                    Fork::waitFor($pid);*/
                } catch (Throwable $e) {
                    $this->outputDated('EXCEPTION: ' . exception($e));
                }
            }
        );

        /**
         * Wait for regular backups to be made.
         */
        Fork::waitWaiting();
        $this->outputDated('Mysql databases were dumped. For full backup to be made make sure that mysql binlog backup is enabled.');
    }

}