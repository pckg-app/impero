<?php namespace Impero\Sites\Controller;

use Impero\Apache\Console\DumpVirtualhosts;
use Impero\Apache\Record\Site;

class Sites
{

    public function postSiteAction()
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
        (new DumpVirtualhosts())->executeManually(['--server' => $data['server_id']]);

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
        $connection = $site->server->getConnection();
        foreach ($commands as $command) {
            $output = null;
            $error = null;
            $output = $connection->exec($command, $error, $site->getHtdocsPath());
            //d($output, $error);
        }
        $connection->close();

        return implode(' ; ', $commands);
    }

    public function postCreateFileAction(Site $site)
    {
        $file = post('file');
        $content = post('content');
        $connection = $site->server->getConnection();
        $connection->sftpSend($content, $site->getHtdocsPath() . $file, null, false);

        return [
            'created' => 'ok',
        ];
    }

}