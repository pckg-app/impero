<?php namespace Impero\Services\Service\Storage\Console;

use Exception;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Impero\Storage\Record\Storage;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\Cron\Fork;
use Throwable;

/**
 * Class MakeStorageBackup
 *
 * @package Impero\Services\Service\Storage\Console
 */
class MakeStorageBackup extends Command
{

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('service:storage:backup')
             ->setDescription('Make cold storage backup and live sync to passive server');
    }

    public function handle()
    {
        /**
         * Receive list of all storage volumes?
         */
        $servers = (new Servers())->withSites()->where('id', 2)->all();

        /**
         * Filter only master servers.
         * First, make zipped backup.
         */
        $servers->each(
            function(Server $server) {
                try {
                    $pid = Fork::fork(
                        function() use ($server) {
                            return;
                            /**
                             * Backup primarly attached volumes.
                             * On zero.gonparty.eu: backup /mnt/volume-fra1-01/live/
                             * This is backed up to cold storage.
                             *
                             * @T00D00 - we should backup each site separately for quicker restore and backup
                             *         - we should also automate restore of multiple sites per once for faster restore
                             * @T00D00 : there are different types of storages:
                             *        - volume: can be backed up by making snapshot
                             *        - root: can be backed up by making zip
                             */
                            $storage = Storage::gets(1);
                            $storage->backup($server);
                        },
                        function() use ($server) {
                            return 'impero:backup:storage:' . $server->id;
                        },
                        function() {
                            throw new Exception('Cannot run storage backup in parallel');
                        }
                    );
                    Fork::waitFor($pid);
                } catch (Throwable $e) {
                    $this->output('EXCEPTION: ' . exception($e));
                }
            }
        );

        Fork::waitWaiting();

        /**
         * Make storage live backups.
         * We can process each server simultaniosly.
         */
        $servers->each(
            function(Server $server) {
                /**
                 * @T00D00 - somehow we need to sync only changed files?
                 */
                try {
                    $pid = Fork::fork(
                        function() use ($server) {
                            return;
                            /**
                             * Backup primarly attached volumes.
                             * On zero.gonparty.eu: backup /mnt/volume-fra1-01/live/
                             * This is backed up to slave / passive storage.
                             */
                            $storage = Storage::gets(1);
                            $storage->backupLive($server);
                        },
                        function() use ($server) {
                            return 'impero:backup:storageLive:' . $server->id;
                        },
                        function() {
                            throw new Exception('Cannot run storage live backup in parallel');
                        }
                    );
                    Fork::waitFor($pid);
                } catch (Throwable $e) {
                    $this->output('EXCEPTION: ' . exception($e));
                }
            }
        );

        Fork::waitWaiting();

        $this->output('Storage backed up');
    }

}