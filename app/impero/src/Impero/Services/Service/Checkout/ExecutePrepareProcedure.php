<?php namespace Impero\Services\Service\Checkout;

use Exception;
use Impero\Apache\Record\Site;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Impero\Services\Service\Helper\SiteAndServer;
use Pckg\Framework\Console\Command;

/**
 * Class ExecutePrepareProcedure
 *
 * @package Impero\Services\Service\Checkout
 */
class ExecutePrepareProcedure extends Command
{

    use SiteAndServer;

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('procedure:prepare:execute')
             ->setDescription('Execute prepare procedure for site on server')
             ->addSiteAndServerOptions();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function handle()
    {
        list($site, $server) = $this->getSiteAndServerOptions();

        $task = Task::create('Preparing site #' . $site->id);

        $task->make(function() use ($server, $site) {
            $this->prepareSiteOnServer($site, $server);
        }, function(Task $task, \Throwable $e) {
            $this->emitErrorEvent();
            throw $e;
        });

        $this->emitEvent('end');

    }

    /**
     * @param Site   $site
     * @param Server $server
     *
     * @throws Exception
     */
    public function prepareSiteOnServer(Site $site, Server $server)
    {
        /**
         * Execute prepare commands.
         */
        $pckg = $site->getImperoPckgAttribute();
        $commands = [];
        foreach ($pckg['prepare'] ?? [] as $command) {
            $site->replaceCommands($commands, $command);
        }
        $connection = $server->getConnection();
        $connection->execMultiple($commands);
    }

}