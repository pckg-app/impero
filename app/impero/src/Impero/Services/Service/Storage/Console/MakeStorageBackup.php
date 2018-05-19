<?php namespace Impero\Services\Service\Storage\Console;

use Exception;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Queue\Service\Cron\Fork;

/**
 * Class MakeStorageBackup
 *
 * @package Impero\Services\Service\Storage\Console
 */
class MakeStorageBackup
{

    public function handle()
    {
        /**
         * Receive list of all storage volumes?
         */
        $servers = (new Servers())->withSites()->where('id', 2)->all();

        /**
         * Filter only master servers.
         */
        $servers->each(
            function(Server $server) {
                try {
                    $pid = Fork::fork(
                        function() use ($server) {
                            /**
                             * Backup primarly attached volumes.
                             * On zero.gonparty.eu: backup /mnt/volume-fra1-01/live/
                             */
                        },
                        function() use ($server) {
                            return 'impero:backup:storage:' . $server->id;
                        },
                        function() {
                            throw new Exception('Cannot run storage backup in parallel');
                        }
                    );
                    Fork::waitFor($pid);
                } catch (\Throwable $e) {
                    /**
                     * @T00D00
                     */
                }
            }
        );

        Fork::waitWaiting();
    }

}