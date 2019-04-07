<?php namespace Impero\Services\Service\Mysql;

use Exception;
use Impero\Apache\Record\Site;
use Impero\Apache\Record\SitesServer;
use Impero\Mysql\Record\Database;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Impero\Services\Service\Helper\SiteAndServer;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Impero\Mysql\Record\User as DatabaseUser;

/**
 * Class DeployMysqlService
 *
 * @package Impero\Services\Service\Mysql
 */
class DeployMysqlService extends Command
{

    use SiteAndServer;

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('service:mysql:deploy')
             ->setDescription('Activa te apache for site on server')
             ->addSiteAndServerOptions();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function handle()
    {
        $this->storeOptions();

        /**
         * Create listed mysql databases.
         */
        if (!isset($this->config['mysql'])) {
            throw new Exception('Mysql config is missing from params');
        }

        $task = Task::create('Preparing database for site #' . $this->site->id);

        $task->make(function() {
            $this->deployDatabaseToServer();
        }, function(Task $task, \Throwable $e) {
            $this->emitErrorEvent();
            throw $e;
        });

        $this->emitFinalEvent();
    }

    /**
     * @param Site   $site
     * @param Server $server
     * @param        $config
     *
     * @throws Exception
     */
    public function deployDatabaseToServer()
    {
        $site = $this->site;
        $server = $this->server;
        $config = $this->config;

        /**
         * Link database to site.
         */
        SitesServer::getOrCreate(['site_id' => $site->id, 'server_id' => $server->id, 'type' => 'database']);

        /**
         * Some defaults.
         */
        $dbpass = auth()->createPassword(20);

        $mysql = $config['mysql'];

        $database = null;
        $dbname = $site->replaceVars($mysql['name']);
        $dbuser = $site->replaceVars(array_keys($mysql['user'])[0]);

        if ($mysql['type'] == 'searchOrCreate') {

            /**
             * Create mysql database, user and set privileges.
             */
            $database = Database::createFromPost([
                                                     'name'      => $dbname,
                                                     'server_id' => $server->id,
                                                 ]);

            /**
             * Manually call backup and replication.
             * Temporary, until we do not automate backups triggered from impero
             */
            $database->requireScriptBackup();
            $database->requireMysqlMasterReplication();
            $database->replicateOnMaster();
        } elseif ($mysql['type'] == 'search') {
            $database = Database::gets([
                                           'server_id' => $server->id,
                                           'name'      => $mysql['name'],
                                       ]);
        }

        /**
         * For configuration.
         */
        $site->mergeImperoVarsAttribute([
                                            '$db' . ucfirst($key) . 'Host' => '127.0.0.1',
                                            '$db' . ucfirst($key) . 'Name' => $dbname,
                                            '$db' . ucfirst($key) . 'User' => $dbuser,
                                            '$db' . ucfirst($key) . 'Pass' => $dbpass,
                                        ]);

        /**
         * Check for access.
         */
        if (!$database) {
            return; // skip?
        }

        foreach ($mysql['user'] ?? [] as $user => $privilege) {
            DatabaseUser::createFromPost([
                                             'username'  => $dbuser,
                                             'password'  => $dbpass,
                                             'server_id' => 2,
                                             'database'  => $database->id,
                                             'privilege' => $privilege,
                                         ]);
        }
    }

}