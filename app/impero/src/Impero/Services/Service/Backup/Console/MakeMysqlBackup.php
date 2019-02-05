<?php namespace Impero\Services\Service\Backup\Console;

use Exception;
use Impero\Mysql\Record\Database;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\Cron\Fork;
use Throwable;

/**
 * Class MakeMysqlBackup
 *
 * @package Impero\Services\Service\Backup\Console
 */
class MakeMysqlBackup extends Command
{

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
        $servers->each(function(Server $server) {
            try {
                $pid = Fork::fork(function() use ($server) {
                    $this->outputDated('Started #' . $server->id . ' cold backup');

                    return;
                    /**
                     * Make backup of each database separately.
                     */
                    $server->databases->each(function(Database $database) {
                        $database->backup();
                    });
                    $this->outputDated('Ended #' . $server->id . ' cold backup');
                }, function() use ($server) {
                    return 'impero:backup:mysql:' . $server->id;
                }, function() {
                    throw new Exception('Cannot run mysql backup in parallel');
                });
                Fork::waitFor($pid);
            } catch (Throwable $e) {
                $this->outputDated('EXCEPTION: ' . exception($e));
            }
        });

        /**
         * Wait for regular backups to be made.
         */
        Fork::waitWaiting();

        /**
         * Make "binlog" live backups.
         * We can process each server simultaniosly.
         */
        $servers->each(function(Server $server) {
            /**
             * @T00D00 - find which server should we sync binlogs to ...
             *         each service on server should probably have it's 'backup:passive' server
             *         mysql on zero.gonparty.eu will have it's backup:passive server set as one.gonparty.eu
             * @T00D00 - in case of mysql outage on zero.gonparty.eu we:
             *           - reconfigure haproxy mysql balancer
             *           - reconfigure slave as master (allow write connections)
             */
            try {
                $pid = Fork::fork(function() use ($server) {
                    $this->outputDated('Started #' . $server->id . ' binlog backup');

                    return;
                    $server->binlogBackup();
                    $this->outputDated('Ended #' . $server->id . ' binlog backup');
                }, function() use ($server) {
                    return 'impero:backup:mysqlBinlog:' . $server->id;
                }, function() {
                    throw new Exception('Cannot run mysql binlog backup in parallel');
                });
                Fork::waitFor($pid);
            } catch (Throwable $e) {
                $this->outputDated('EXCEPTION: ' . exception($e));
            }
        });

        /**
         * Wait for binlog backups to be made.
         */
        Fork::waitWaiting();

        $this->outputDated('Mysql backed up');
    }

    /**
     *
     */
    public function backupBinlogs()
    {
        $command = 'mysqlbinlog -u fullremote -p --read-from-remote-server --host=hardisland.com --raw mysql-bin.002157';
        // --start-position=N, -j N
        // --stop-position=N
        // --stop-never
        // mysqlbinlog binlog.000001 | mysql -u root -p
        // --binary-mode
        // mysqlbinlog binlog.000001 > tmpfile
    }

    /**
     *
     */
    public function restoreMysqlMasterDatabase()
    {
        /**
         * Get and decrypt latest mysql dump.
         * Get binlogs between dump and till the end.
         * Import mysql dump.
         */
    }

    /**
     *
     */
    public function restoreMysqlSlaveDatabase()
    {
        /**
         * Get and decrypt latest mysql dump.
         * Get binlogs between backup and slave.
         * Stop slave.
         * Import mysql dump.
         * Sync dump with slave from binlogs.
         * Resume slave.
         */
    }

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('service:mysql:backup')
             ->setDescription('Make cold backup of mysql databases and live binlog sync');
    }

}