<?php namespace Impero\Sites\Controller;

use Exception;
use Impero\Apache\Entity\SitesServers;
use Impero\Apache\Record\Site;
use Impero\Apache\Record\SitesServer;
use Impero\Mysql\Entity\Databases;
use Impero\Mysql\Record\Database;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Pckg\Generic\Record\SettingsMorph;
use Pckg\Mail\Service\Mail\Adapter\SimpleUser;
use Throwable;

class Sites
{

    public function getSiteAction(Site $site)
    {
        return [
            'site' => $site,
        ];
    }

    public function deleteSiteAction(Site $site)
    {
        $deleteUrl = url('impero.site.confirmDelete', ['site' => $site], true) . '?hash=' . $site->hash;

        email(
            [
                'subject' => 'Confirm /impero site #' . $site->id . ' (' . $site->server_name . ') removal',
                'content' => '<p>Hey Bojan,</p>'
                    . '<p>someone requested removal of site ' . $site->id . ' (' . $site->server_name . ').</p>'
                    . '<p>This action will create a backup of database, storage and config, remove app from all 
servers and services, delete all related backups (except final one :)). Backup will still be available for 14 days
for manual reuse.</p>'
                    . '<p>If you really want to delete site and all it\'s contents, please login to /impero and click '
                    . '<a href="' . $deleteUrl . '">here</a>.'
                    . '</p>'
                    . '<p>Best regards, /impero team</p>',
            ],
            new SimpleUser('schtr4jh@schtr4jh.net')
        );

        return [
            'success' => true,
            'message' => 'System administrator notified about request',
        ];
    }

    /**
     * @param Site $site
     *
     * @return array
     * @throws Exception
     */
    public function getConfirmDeleteAction(Site $site)
    {
        if ($site->hash != get('hash', null)) {
            throw new Exception('Not matching hashes!');
        }

        if (false) {
            email(
                [
                    'subject' => 'Confirmation of /impero site #' . $site->id . ' (' . $site->server_name . ') removal',
                    'content' => '<p>Hey Bojan,</p>'
                        . '<p>site ' . $site->id . ' (' . $site->server_name . ') was deleted on you request.</p>'
                        . '<p>Before we deleted *everything* we made storage, database and config backup which will be 
available for another 14 days in /impero dashboard in case of missdelete. After 14 days backup files will be deleted 
automatically and permanently.</p>'
                        . '<p>Best regards, /impero team</p>',
                ],
                new SimpleUser('schtr4jh@schtr4jh.net')
            );

            $site->undeploy([], []);
        }

        return [
            'success' => true,
            'message' => 'Platform undeployed',
        ];
    }

    public function postCreateAction()
    {
        $data = only(post()->all(), ['user_id', 'server_id', 'name', 'aliases', 'ssl']);

        /**
         * Site is only created, it is not deployed in any way.
         * Services are enabled manually.
         */
        $site = Site::create(
            [
                'server_name'   => $data['name'],
                'server_alias'  => $data['aliases'],
                'user_id'       => $data['user_id'],
                'error_log'     => 1,
                'access_log'    => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'document_root' => $data['name'],
                'server_id'     => $data['server_id'],
            ]
        );

        return [
            'site' => $site,
        ];
    }

    public function postExecAction(Site $site)
    {
        set_time_limit(60 * 5);
        /**
         * Commands are sent in action post.
         */
        $commands = post('commands', []);
        $vars = post('vars', []);
        $connection = $site->server->getConnection();
        foreach ($commands as $command) {
            $output = null;
            $error = null;
            $command = $vars ? $site->replaceVars($command, $vars) : $command;
            $output = $connection->exec($command, $error, $site->getHtdocsPath() . post('cd', null));
        }
        $connection->close();

        return implode(' ; ', $commands);
    }

    public function postCreateFileAction(Site $site)
    {
        $file = post('file');
        $content = post('content');

        $site->createFile($file, $content);

        return [
            'created' => 'ok',
        ];
    }

    public function postLetsencryptAction(Site $site)
    {
        $site->letsencrypt();

        return [
            'success' => true,
        ];
    }

    public function postCronjobAction(Site $site)
    {
        $site->addCronjob(post('command'));

        return ['success' => true];
    }

    public function postHasSiteDirAction(Site $site)
    {
        return [
            'hasSiteDir' => $site->hasSiteDir(post('dir')),
        ];
    }

    public function postHasRootDirAction(Site $site)
    {
        return [
            'hasRootDir' => $site->hasRootDir(post('dir')),
        ];
    }

    public function postHasSiteSymlinkAction(Site $site)
    {
        return [
            'hasSiteSymlink' => $site->hasSiteSymlink(post('symlink')),
        ];
    }

    public function postHasSiteFileAction(Site $site)
    {
        return [
            'hasSiteFile' => $site->hasSiteFile(post('file')),
        ];
    }

    /**
     * @param Site $site
     *
     * @return array
     * @throws Exception
     */
    public function postSetDomainAction(Site $site)
    {
        $domain = post('domain', null);
        $domains = post('domains', null);

        if (!$domain) {
            throw new Exception('Domain is required');
        }

        $site->setAndSave(['server_name' => $domain, 'server_alias' => $domains]);
        if (post('letsencrypt')) {
            $site->letsencrypt();
        } else if (post('restart_apache')) {
            $site->restartApache();
        }

        return [
            'site' => $site,
        ];
    }

    /**
     * @param Site $site
     *
     * @return array
     * @throws Exception
     */
    public function postCheckoutAction(Site $site)
    {
        /**
         * Pckg project definition and variables are stored on initial checkout.
         * Settings can be changed and added afterwards.
         */
        SettingsMorph::makeItHappen('impero.pckg', json_encode(post('pckg', [])), \Impero\Apache\Entity\Sites::class, $site->id);
        SettingsMorph::makeItHappen('impero.vars', json_encode(post('vars', [])), \Impero\Apache\Entity\Sites::class, $site->id);

        /**
         * Whole project (or site) is then initially checked out on server.
         * User may choose multiple servers / targets for specific service.
         */
        $site->checkout($site->server);

        return [
            'site' => $site,
        ];
    }

    /**
     * @param Site $site
     *
     * @return array
     * @throws Exception
     */
    public function postRecheckoutAction(Site $site)
    {
        $vars = array_merge($site->getImperoVarsAttribute(), post('vars', []));
        $pckg = post('pckg', $site->getImperoPckgAttribute());

        $site->setImperoVarsAttribute($vars);
        $site->setImperoPckgAttribute($pckg);

        $site->recheckout($site->server);

        return [
            'site' => $site,
        ];
    }

    /**
     * @param Site $site
     *
     * @return array
     * @throws Exception
     */
    public function postDeployAction(Site $site)
    {
        /**
         * Currently only mailo is deployed to different servers.
         * Mailo is directly checked-out so we can pull on both servers. Migrate only first instance.
         * Linked checkouts pulls only linked directories. Migrates first instance of each site.
         */
        $task = Task::create('Deploying site #' . $site->id);

        $task->make(
            function() use ($site) {
                $migrate = true;
                $site->sitesServers->filter('type', 'web')->each(
                    function(SitesServer $sitesServer) use ($site, &$migrate) {

                        $site->deploy(
                            $sitesServer->server,
                            post('pckg', []),
                            post('vars', []),
                            post('isAlias', false),
                            post('checkAlias', false),
                            $migrate
                        );
                        $migrate = false;
                    }
                );
            }
        );

        return [
            'site' => $site,
        ];
    }

    public function postCheckAction(Site $site)
    {
        return ['check' => $site->check(post('pckg', []))];
    }

    public function getCronjobsAction()
    {
        return ['cronjobs' => ['yes!']];
    }

    /**
     * @param Site   $site
     * @param Server $server
     *
     * @throws Exception
     */
    public function postWebWorkerAction(Site $site, Server $server)
    {
        $variables = post('vars', []);
        $pckg = post('pckg', []);

        $site->addWebWorker($server, $pckg, $variables);

        return [
            'success' => true,
        ];
    }

    /**
     * @param Site   $site
     * @param Server $server
     *
     * @throws Exception
     */
    public function postCronWorkerAction(Site $site, Server $server)
    {
        $variables = post('vars', []);
        $pckg = post('pckg', []);

        $site->addCronWorker($server, $pckg, $variables);

        return [
            'success' => true,
        ];
    }

    /**
     * Add site's databases to another slave.
     *
     * @param Site $site
     *
     * @return array
     */
    public function postMysqlSlaveAction(Site $site, Server $server)
    {
        /**
         * First, get databases associated with site.
         * They are defined in pckg.yaml.
         * They should also be associated with different sites, which are currently not.
         * We will associate them in databases_morphs table (can be associated with servers, users, sites, ...).
         */
        $variables = post('vars', []);
        $pckg = post('pckg', []);

        /**
         * Now we have list of all databases (id_shop and pckg_derive for example) and we need to check that replication is in place.
         */
        $databases = [];
        foreach ($pckg['service']['db']['mysql']['database'] ?? [] as $database => $config) {
            $databases[] = str_replace(array_keys($variables), array_values($variables), $database['name']);
        }

        if (!$databases) {
            return ['success' => false, 'message' => 'No databases'];
        }

        $databases = (new Databases())->where('name', $databases)->all();
        $databases->each(
            function(Database $database) use ($server, $site) {
                $sitesServer = SitesServer::getOrNew(
                    [
                        'site_id'   => $site->id,
                        'server_id' => $server->id,
                        'type'      => 'database:slave',
                    ]
                );
                if (!$sitesServer->isNew()) {
                    /**
                     * Skip existing?
                     *
                     * @T00D00 ... at some point site may have multiple different databases over different servers
                     *         ... link database with server instead
                     * @T00D00 ... implement connections
                     *         ... serve with server: zero@eth1 - one@eth1
                     *         ... database with site
                     *         ... database with server
                     */
                    return;
                }

                /**
                 * Check that master is configured as master.
                 */
                $database->requireMysqlMasterReplication();

                /**
                 * Check that binlog is actually created for database.
                 */
                $database->replicateOnMaster();

                /**
                 * Make backup and enable replication on slae.
                 */
                $database->replicateTo($server);

                /**
                 * When successfuly, link database:slave service to it.
                 */
                if ($sitesServer->isNew()) {
                    $sitesServer->save();
                }
            }
        );

        return [
            'success'   => true,
            'databases' => $databases->map('name'),
        ];
    }

    public function getInfrastructureAction(Site $site)
    {
        return [
            'success'        => true,
            'infrastructure' => $site->getInfrastructure(),
        ];
    }

    public function postChangeVariableAction(Site $site)
    {
        $vars = post()->all();

        /**
         * First thing is to save new variables.
         */
        $currentVars = $site->getImperoVarsAttribute();
        $site->setImperoVarsAttribute(array_merge($currentVars, $vars));

        /**
         * Now we need to find all services that are using variables.
         * The only service currently known is cronjob.
         * We will remove old cronjob and install new one.
         */
        $crons = (new SitesServers())->where('type', 'cron')->where('site_id', $site->id)->all();
        $crons->each->redeploy();

        /**
         * The other currently known change is config change.
         * Config files are defined in checkout.config section in pckg.yaml configuration file.
         * Checkout variables are saved in impero.vars settings on site level.
         * We need to retrieve them from config for existing platforms.
         * If they're not available in settings, we need to manually set them (warning & import @ center?).
         * We need to delete old files (they should be logged somewhere) and recreate new ones.
         * When config file is removed from pckg.yaml we need to remove it.
         */
        $configs = (new SitesServers())->where('type', 'config')->where('site_id', $site)->all();
        $configs->each->redeploy();

        return [
            'success' => true,
        ];
    }

    public function postChangePckgAction(Site $site)
    {
        $pckg = post('pckg');
        $site->setImperoPckgAttribute($pckg);

        return [
            'success' => true,
        ];
    }

    public function getVarsAction(Site $site)
    {
        return [
            'vars' => $site->getImperoVarsAttribute(),
        ];
    }

    public function postVarsAction(Site $site)
    {
        return [
            'vars' => post('vars'),
        ];
    }

    public function postFileContentAction(Site $site) {
        return [
            'content' => $site->getServerConnection()->sftpRead($site->getHtdocsPath() . post('file')),
        ];
    }

    public function postRedeployCronServiceAction(Site $site)
    {
        (new SitesServers())->where('site_id', $site->id)->where('type', 'cron')
            ->allAndEach(function(SitesServer $sitesServer){
                $sitesServer->redeploy();
            });

        return [
            'site' => $site,
        ];
    }

    public function postRedeploySslServiceAction(Site $site)
    {
        $site->letsencrypt();

        return [
            'site' => $site,
        ];
    }

    public function postRedeployConfigServiceAction(Site $site)
    {
        $task = Task::create('Config service redeploy - site #' . $site->id);

        /**
         * Task may take long to execute, respond with success and continue with execution.
         *
         * @T00D00 - how will we communicate possible errors?
         */
        response()->respondAndContinue([
                                           'success' => true,
                                           'site'    => $site,
                                           'task'    => $task,
                                       ]);

        $task->make(function() use ($site) {
            (new SitesServers())->where('site_id', $site->id)->where('type', 'config')->allAndEach(function(
                SitesServer $sitesServer
            ) {
                $sitesServer->redeploy();
            });
        },
            function(Task $task, Throwable $e) {
                /**
                 * Exception was thrown, task is already marked as error.
                 * Can we log exception to rollbar?
                 * Can we notify admin about exception?
                 */
                throw $e;
            });
    }

}