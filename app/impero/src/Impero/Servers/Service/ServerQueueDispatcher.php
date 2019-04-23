<?php namespace Impero\Servers\Service;

use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Command\RunChannel;
use Pckg\Queue\Service\Cron\Fork;

class ServerQueueDispatcher extends Command
{

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('service:dispatch-workers')->setDescription('Dispatch workers for all servers and services');
    }

    public function handle()
    {
        /**
         * Find all active servers.
         * Find all active services.
         * Start working for added services.
         * Stop working for removed services
         */
        $servers = (new Servers())->where('id', [2, 3, 4])->all();

        $servers->each(function(Server $server) {
            /**
             * Receive all active services.
             */
            $services = $this->getActiveServices();

            /**
             * Start listening for events and execute them.
             */
            foreach ($services as $service) {
                $channel = 'impero/impero/servers/' . $server->id . '/' . $service;

                $pid = Fork::fork(function() use ($channel) {
                    /**
                     * Now we can start listening to process and execute tasks.
                     * This is long running script.
                     * We should implement stop event for which all services should be listening for gracefull shutdown.
                     */
                    (new RunChannel())->executeManually([
                                                            '--channel'     => $channel,
                                                            '--concurrency' => 1,
                                                        ]);
                }, function() use ($channel) {
                    return 'service:dispatch-workers --channel=' . $channel;
                });
                Fork::waitFor($pid);
            }
        });

        Fork::waitWaiting(30, function() {
            /**
             * We can check if any additional worker needs to be dispatched of if it has failed?
             */
        });
    }

    public function getActiveServices()
    {
        return [
            /**
             * Frontend services
             */
            'service:apache',
            'service:nginx',
            'service:haproxy',
            /**
             * Backend services
             */
            'service:cron',
            'service:config',
            /**
             * Database and storage
             */
            'resource:mysql',
            'resource:queue',
            'resource:cache',
            'resource:storage',
        ];
    }

}