<?php namespace Impero\Services\Service\Backup\Console;

use Exception;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Queue\Service\Cron\Fork;
use Throwable;

/**
 * Class MakeMysqlBackup
 *
 * @package Impero\Services\Service\Backup\Console
 */
class MakeSystemBackup
{

    /**
     *
     */
    public function handle()
    {
        $servers = (new Servers())->all();

        $servers->each(
            function(Server $server) {
                try {
                    Fork::fork(
                        function() use ($server) {
                            /**
                             * Throw backup event for all services that are listening for backup event. :)
                             */
                        },
                        function() {
                            return 'impero:backup:system';
                        },
                        function() {
                            throw new Exception('Cannot run system backup in parallel');
                        }
                    );
                } catch (Throwable $e) {
                    /**
                     * @T00D00 - log error
                     */
                }
            }
        );
    }

}