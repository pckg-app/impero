<?php namespace Impero\Services\Service\Storage\Console;

use Exception;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Database\Relation\HasMany;
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
        $servers = (new Servers())->withSites(
            function(HasMany $sites) {

            }
        )->all();

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
                             */
                        },
                        function() use ($server) {
                            return 'impero:backup:storage';
                        },
                        function() {
                            throw new Exception('Cannot run storage backup in parallel');
                        }
                    );
                } catch (\Throwable $e) {
                    /**
                     * @T00D00
                     */
                }
            }
        );
    }

}