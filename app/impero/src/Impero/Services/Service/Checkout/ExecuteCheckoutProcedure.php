<?php namespace Impero\Services\Service\Checkout;

use Exception;
use Impero\Apache\Record\Site;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Impero\Services\Service\Helper\SiteAndServer;
use Pckg\Framework\Console\Command;

/**
 * Class ExecuteCheckoutProcedure
 *
 * @package Impero\Services\Service\Checkout
 */
class ExecuteCheckoutProcedure extends Command
{

    use SiteAndServer;

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('procedure:checkout:execute')
             ->setDescription('Activa te apache for site on server')
             ->addSiteAndServerOptions();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function handle()
    {
        list($site, $server) = $this->getSiteAndServerOptions();

        $task = Task::create('Checking out site #' . $site->id . ' on server #' . $server->id);

        $task->make(function() use ($server, $site) {
            $this->checkoutSiteOnServer($site, $server);
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
    public function checkoutSiteOnServer(Site $site, Server $server)
    {
        /**
         * All commands will be executed in platform's htdocs path.
         */
        $pckg = $site->getImperoPckgAttribute();

        if ($pckg['checkout']['type'] == 'ab') {
            $this->abCheckout();
        } elseif ($pckg['checkout']['type'] == 'linked') {
            $this->linkedCheckout($site, $server);
        } else { // default type = multiple
            $this->defaultCheckout($site, $server);
        }
    }

    /**
     * @param Site   $site
     * @param Server $server
     *
     * @throws Exception
     */
    protected function defaultCheckout(Site $site, Server $server)
    {
        $connection = $server->getConnection();
        $pckg = $site->getImperoPckgAttribute();

        /**
         * Standalone platforms clones git repository and checkouts branch
         */
        $commands = [
            'git clone ' . $pckg['repository'] . ' .',
            'git checkout ' . $pckg['branch'],
        ];

        /**
         * We also execute project defined commands for initialization like dependency install.
         */
        foreach ($pckg['init'] as $initCommand) {
            $site->replaceCommands($commands, $initCommand);
        }

        $errorStream = null;
        $outputStream = null;
        $connection->execMultiple($commands, $outputStream, $errorStream, $site->getHtdocsPath());

        $this->emitEvent('source:ready');
    }

    /**
     * @param Site   $site
     * @param Server $server
     *
     * @throws Exception
     */
    protected function linkedCheckout(Site $site, Server $server)
    {
        $connection = $server->getConnection();
        $commands = [];
        $pckg = $site->getImperoPckgAttribute();
        /**
         * Htdocs directory will point to /www/_linked/$repository/$branch/
         */
        /**
         * We need to make sure that repository and branch are already checked-out on filesystem.
         */
        $aliasDir = $site->getLinkedDir($pckg);
        $site->prepareLinkedCheckout($server, $pckg, $aliasDir);

        $this->emitEvent('source:ready');

        /**
         * Create some dirs, such as www and cache in which we will probably mount some files.
         * We won't store any data in those directories.
         */
        foreach ($pckg['checkout']['create']['dir'] as $dir) {
            $commands[] = 'mkdir ' . $site->getHtdocsPath() . $dir;
        }

        /**
         * Create dir and file symlinks for shared stuff.
         */
        foreach ($pckg['checkout']['symlink']['dir'] as $dir) {
            $commands[] = 'ln -s ' . $aliasDir . $dir . ' ' . $site->getHtdocsPath() . $dir;
        }
        foreach ($pckg['checkout']['symlink']['file'] as $file) {
            $commands[] = 'ln -s ' . $aliasDir . $file . ' ' . $site->getHtdocsPath() . $file;
        }

        $connection->execMultiple($commands);
    }

    /**
     * @param Site   $site
     * @param Server $server
     */
    protected function abCheckout(Site $site, Server $server)
    {
        /**
         * Htdocs directory will point to /www/_linked/$repository/$commit/
         */
        /**
         * When multiple containers on host share same checkout each points to specific commit.
         * There are directories:
         *       - /www/_ab/$repository/$branch/shacommit1
         *       - /www/_ab/$repository/$branch/shacommit2
         *       - ...
         * When 1 worker is active:
         *  - apache and nginx point shacommit1 directory
         *  - we perform deploy in new directory shacommit2
         *  - we simply switch /www/client/project/htdocs/ from shacommit1 to shacommit2 and reload services
         * When multiple workers are active
         *  - apache and nginx point shacommit1 directory
         *  - we perform deploy in new directory shacommit2
         *  - we put first worker down, change htdocs to shacommit2, reload services, put first worker up
         *  - we put next worker down, change htdocs to shacommit2, reload services, put worker up
         *  - ...
         */
    }

}