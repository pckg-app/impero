<?php namespace Impero\Services\Service\Storage\Console;

use Exception;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Database\Relation\HasMany;
use Pckg\Queue\Service\Cron\Fork;

/**
 * Class MakeConfigBackup
 *
 * @package Impero\Services\Service\Storage\Console
 */
class MakeConfigBackup
{

    public function handle()
    {
        /**
         * Receive list of all web servers?
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
                             * Backup env/config.php for each platform.
                             */
                        },
                        function() use ($server) {
                            return 'impero:backup:config';
                        },
                        function() {
                            throw new Exception('Cannot run config backup in parallel');
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