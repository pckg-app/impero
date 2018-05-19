<?php namespace Impero\Services\Service\Storage\Console;

use Exception;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Database\Relation\HasMany;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\Cron\Fork;
use Throwable;

/**
 * Class MakeConfigBackup
 *
 * @package Impero\Services\Service\Storage\Console
 */
class MakeConfigBackup extends Command
{

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('service:config:backup')
             ->setDescription('Make backup of config/env.php files');
    }

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
                            return;
                            /**
                             * @T00D00 - backup config/env.php files
                             */
                        },
                        function() use ($server) {
                            return 'impero:backup:config:' . $server->id;
                        },
                        function() {
                            throw new Exception('Cannot run config backup in parallel');
                        }
                    );
                    Fork::waitFor($pid);
                } catch (Throwable $e) {
                    $this->output('EXCEPTION: ' . exception($e));
                }
            }
        );

        Fork::waitWaiting();

        $this->output('Config backed up');
    }

}