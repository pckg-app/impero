<?php namespace Impero\Services\Service\Backup\Console;

use Impero\Mysql\Record\Database;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Database\Relation\HasMany;

/**
 * Class MakeMysqlBackup
 *
 * @package Impero\Services\Service\Backup\Console
 */
class MakeMysqlBackup
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
        $servers = (new Servers())->withSites(function (HasMany $sites) {

        })->all();

        /**
         * Make "cold" backup - transactional mysql dump.
         * We can process each server simultaniosly.
         */
        $servers->each(function (Server $server) {
            $server->databases->each(function (Database $database) {
                $database->backup();
            });
        });

        /**
         * Make "binlog" live backups.
         * We can process each server simultaniosly.
         */
        $servers->each(function (Server $server) {

        });
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

}