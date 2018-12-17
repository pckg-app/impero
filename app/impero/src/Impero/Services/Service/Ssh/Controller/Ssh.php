<?php namespace Impero\Services\Service\Ssh\Controller;

use Impero\Servers\Record\Server;
use Impero\Services\Service\Ssh as SshService;
use Impero\Services\Service\Ssh\Form\ServerSettings;

class Ssh
{

    public function postServerSettingsAction(Server $server, ServerSettings $serverSettings)
    {
        $settings = $serverSettings->getData();
        (new SshService($server->getConnection()))->processSettings($settings, $server);

        return [
            'settings' => $settings,
        ];
    }

}