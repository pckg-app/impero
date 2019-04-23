<?php namespace Impero\Sites\Console;

use Exception;
use Impero\Apache\Record\Site;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\Cron\Fork;
use Pckg\Queue\Service\RabbitMQ;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Input\InputOption;

class CheckoutSite extends Command
{

    /**
     * @var Site
     */
    protected $site;

    /**
     * @var array
     */
    protected $scale;

    /**
     * @var RabbitMQ
     */
    protected $broker;

    public function configure()
    {
        $this->setName('site:checkout')->setDescription('Deploy services to server')->addOptions([
                                                                                                     'site'  => 'Site ID',
                                                                                                     'scale' => 'Scale configuration',
                                                                                                 ],
                                                                                                 InputOption::VALUE_REQUIRED);
    }

    /**
     * @return RabbitMQ
     */
    public function getRabbitMQ()
    {
        return resolve(RabbitMQ::class);
    }

    /**
     * Get definition for how services depends on each other.
     *
     * @return array
     */
    protected function getBringUp()
    {
        return $this->site->getImperoPckgAttribute()['bring-up'] ?? [];
    }

    /**
     * Split jobs to immediate and dependent / waiting.
     *
     * @return array
     */
    protected function splitWorkers()
    {
        $bringUp = $this->getBringUp();

        $immediately = [];
        $waiting = [];

        foreach ($bringUp as $namespace => $config) {
            if (!$config) {
                $immediately[] = $namespace;
                continue;
            }

            $waiting[$namespace] = $config;
        }

        return [$immediately, $waiting];
    }

    /**
     * New Code
     * We have finally break down the code to a point where deployment is abstract.
     * We have services, resources or commands that depends on other states.
     * This is a place where everything gets dispatched.
     * So this is where we create all needed RabbitMQ channels and listen on them.
     * Not-dependant parts can be queued immediately to server:service queue.
     * Dependant parts will be fired immediately after all requirements are met.
     * Requirements are met when all needed events are fired.
     * Known resources: mysql, redis, rabbitmq
     * Known commands: checkout, prepare
     * Known services: web, web-static, web-dynamic, cron, queue
     *
     * @throws Exception
     */
    public function handle()
    {
        $site = $this->option('site');
        if (!$site) {
            throw new Exception('Site is required');
        }

        $this->site = Site::getOrFail($site);
        $this->scale = $this->decodeOption('scale', true);

        if (!$this->scale) {
            throw new Exception('Scale option is not readable');
        }

        /**
         * Split actions to immediate and dependent / waiting.
         */
        list($immediately, $waiting) = $this->splitWorkers();
        $this->outputDated('Immediately: ' . implode(', ', $immediately));
        $this->outputDated('Delayed: ' . implode(', ', array_keys($waiting)));
        $finished = [];

        if (!$immediately) {
            $this->outputDated('ERROR: No immediate events, exiting');
            $this->outputDated(json_encode($this->getBringUp()));
            return;
        }

        /**
         * Register listeners.
         */
        $channel = 'impero/impero/sites/' . $this->site->id . '/checkout';
        $this->outputDated('Listening to shout on ' . $channel);
        $this->broker = $this->getRabbitMQ();
        $this->broker->makeShoutExchange($channel);
        $this->broker->prepareToListenShouted($channel, $this->getListenerCallback($waiting, $finished));

        /**
         * Immediately fire independent.
         */
        $this->outputDated('Running independent');
        foreach ($immediately as $step) {
            $this->runStep($step);
        }

        /**
         * Listen until all conditions are met.
         */
        $this->broker->sleepCallbacks(function() use (&$waiting, &$finished) {
            $finish = !$waiting && count($finished) >= count($this->scale);

            if (!$finish) {
                $this->outputDated('Waiting for: ' . json_encode($waiting));

                sleep(1);
            }

            $this->outputDated('Finished: ' . ($finish ? 1 : 0) . ' ' . count($finished) . ' ' . count($this->scale));

            return $finish;
        });

        /**
         * Close broker connection and exit.
         */
        $this->outputDated('Closing broker');
        $this->broker->close();
        $this->outputDated('Done');
    }

    protected function getListenerCallback(&$waiting, &$finished)
    {
        return function(AMQPMessage $message) use (&$waiting, &$finished) {
            $this->outputDated('Got message ' . $message->body);
            /**
             * We want to receive JSON.
             */
            $json = json_decode($message->body, true);

            /**
             * Check for event state.
             */
            $state = $json['state'] ?? null;
            if (!$state == 'error') {
                throw new Exception('Got error state from sub-event');
            }

            /**
             * We will check which event was set and remove conditions.
             */
            $finished[] = $json;
            foreach ($waiting as $namespace => &$config) {
                /**
                 * We're looking for wrong event.
                 */
                if (!in_array($json['event'], $config['when'])) {
                    continue;
                }

                /**
                 * Event was fired, remove it.
                 */
                unset($config['when'][array_search($json['event'], $config['when'])]);

                /**
                 * We still have some dependencies.
                 */
                if ($config['when']) {
                    continue;
                }

                /**
                 * Remove from waiting list.
                 */
                unset($waiting[$namespace]);

                /**
                 * Run step.
                 */
                $this->outputDated('Running next step ' . $namespace);
                $this->runStep($namespace);
            }
        };
    }

    public function runStep($step)
    {
        /**
         * We have step (as resource:database) and we need to determine servers.
         */
        $servers = $this->scale[$step] ?? [];

        /**
         * Break when no servers are set.
         */
        if (!$servers) {
            $this->outputDated('No servers for ' . $step);
            return;
        }

        /**
         * Prepare array of servers.
         */
        if (!is_array($servers)) {
            $servers = [$servers];
        }

        /**
         * Mysql example, where we can define servers for different levels (master or slave).
         */
        if (!is_associative_array($servers)) {
            /**
             * @T00D00 - How to determine what should happen?
             */
            $this->outputDated('Invalid definition for ' . $step);
            return;
        }

        /**
         * Deploy service on each server.
         */
        $servers = (new Servers())->where('id', $servers)->all();
        $servers->each(function(Server $server) use ($step) {
            $this->runStepOnServer($step, $server);
        });
    }

    protected function getCallbackAndConfig($step)
    {
        /**
         * $type - resource, service, command
         * $name - database, queue, cache, web-dynamic, web-static, checkout, storage, config, prepare, cron
         */
        $pckg = $this->site->getImperoPckgAttribute();
        list($type, $name) = explode(':', $step);

        /**
         * So, we now have config for service or resource.
         * We currently support web (apache) and db (mysql) services.
         */
        $callback = null;
        $trigger = null;
        $service = null;
        $config = [];
        if ($type == 'command') {
            /**
             * Only official commands are supported here.
             */
            $callback = 'procedure:' . $name . ':execute';
            $service = 'command:' . $name;
        } elseif ($type == 'service' || $type == 'resource') {
            /**
             * Skip when no config is defined - there's nothing to deploy actually?
             * resource -> resources
             * service -> services
             */
            $config = $pckg[$type . 's'][$name] ?? [];

            if (isset($config['apache'])) {
                $callback = 'service:apache:deploy';
                $service = 'service:apache';

            } elseif (isset($config['command'])) {
                $callback = 'service:cron:deploy';
                $service = 'service:cron';

            } elseif (isset($config['config'])) {
                $callback = 'service:config:deploy';
                $service = 'service:config';

            } elseif (isset($config['mysql'])) {
                $callback = 'resource:mysql:deploy';
                $service = 'resource:mysql';

            } elseif (isset($config['volumes'])) {
                $callback = 'resource:storage:deploy';
                $service = 'resource:storage';

            }
        };

        return [$callback, $config, $service];
    }

    public function runStepOnServer($step, Server $server)
    {
        list($callback, $config, $service) = $this->getCallbackAndConfig($step);

        /**
         * Skip when no callback.
         */
        if (!$callback) {
            $this->outputDated('No callback for ' . $step);
            return;
        }

        /**
         * Queue command to server.
         * This way we can execute commands in order and concurrently.
         * Each of mapped actions is be callable from command, example DeployApacheService.
         */
        $this->outputDated('Running ' . $callback .' for ' . $step . ' on #' . $server->id);
        $channel = 'impero/impero/servers/' . $server->id . '/' . $service;
        $respondChannel = 'impero/impero/sites/' . $this->site->id . '/checkout';
        $data = [
            'site'   => $this->site->id,
            'server' => $server->id,
            'config' => $config,
            'shout'  => [
                'event'   => $step,
                'channel' => $respondChannel,
            ],
        ];
        $this->outputDated('Posting to channel ' . $channel . ' command ' . $callback);
        $this->outputDated(json_encode($data));
        queue($channel, $callback, $data);
    }

}