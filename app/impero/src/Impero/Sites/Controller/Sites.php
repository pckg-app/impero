<?php namespace Impero\Sites\Controller;

use Exception;
use Impero\Apache\Record\Site;

class Sites
{

    public function getSiteAction(Site $site)
    {
        return [
            'site' => $site,
        ];
    }

    public function postCreateAction()
    {
        $data = only(post()->all(), ['user_id', 'server_id', 'name', 'aliases', 'ssl']);

        $site = Site::create([
                                 'server_name'   => $data['name'],
                                 'server_alias'  => $data['aliases'],
                                 'user_id'       => $data['user_id'],
                                 'error_log'     => 1,
                                 'access_log'    => 1,
                                 'created_at'    => date('Y-m-d H:i:s'),
                                 'document_root' => $data['name'],
                                 'server_id'     => $data['server_id'],
                             ]);

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

}