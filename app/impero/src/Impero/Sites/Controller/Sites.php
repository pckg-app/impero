<?php namespace Impero\Sites\Controller;

use Exception;
use Impero\Apache\Record\Site;
use Impero\Mysql\Entity\Databases;
use Impero\Mysql\Record\Database;
use Impero\Servers\Record\Server;
use Pckg\Mail\Service\Mail\Adapter\SimpleUser;

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
                'subject' => 'Confirm /impero site #' . $site->id . ' (' . $site->title . ') removal',
                'content' => '<p>Hey Bojan,</p>'
                    . '<p>someone requested removal of site ' . $site->id . ' (' . $site->title . '.</p>'
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
     * @throws
     */
    public function getConfirmDeleteAction(Site $site)
    {
        if ($site->hash != get('hash', null)) {
            throw new Exception('Not matching hashes!');
        }

        if (false) {
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

        $site->createOnFilesystem();
        $site->restartApache();

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

    public function postSetDomainAction(Site $site)
    {
        $domain = post('domain', null);
        $domains = post('domains', null);

        if (!$domain) {
            throw new Exception('Domain is required');
        }

        $site->setAndSave(['server_name' => $domain, 'server_alias' => $domains]);
        if (post('restart_apache')) {
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
     */
    public function postCheckoutAction(Site $site)
    {
        $site->checkout(post('pckg', []), post('vars', []));

        return [
            'site' => $site,
        ];
    }

    public function postRecheckoutAction(Site $site)
    {
        $site->recheckout(post('pckg', []), post('vars', []));

        return [
            'site' => $site,
        ];
    }

    public function postDeployAction(Site $site)
    {
        $site->deploy(post('pckg', []), post('vars', []), post('isAlias', false), post('checkAlias', false));

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
            return ['success' => false];
        }

        $databases = (new Databases())->where('server_id', $server->id)->where('name', $databases)->all();
        $databases->each(
            function(Database $database) use ($server) {
                $database->requireMysqlMasterReplication();
                $database->replicateOnMaster();
                $database->replicateTo($server);
            }
        );

        return [
            'success'   => true,
            'databases' => $databases->map('name'),
        ];
    }

}