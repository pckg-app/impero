<?php namespace Impero\Services\Service;

use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Pckg\Generic\Record\SettingsMorph;

/**
 * Class Ssh
 *
 * @package Impero\Services\Service
 */
class Ssh extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'ssh';

    /**
     * @var string
     */
    protected $name = 'SSH';

    /**
     * @return bool|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()->exec('ssh -V');

        $length = strpos($response, ",");

        return substr($response, 0, $length);
    }

    public function processSettings(array $settings, Server $server, $force = false)
    {
        /**
         * If force flag is set, force settings on server.
         * Otherwise check and compare data with current settings.
         * Change data on server only when settings doesn't match.
         * We currently support Port, AllowRootLogin, LoginGraceTime and PasswordAuthentication settings.
         */
        $inSync = true;
        if (!$server->hasSetting('impero.service.ssh.sshPort')) {
            $port = $server->port;
            SettingsMorph::makeItHappen('impero.service.ssh.sshPort', $port, Servers::class, $server->id);
            $inSync = false;
        } else {
            $port = $server->getSettingValue('impero.service.ssh.sshPort');
        }

        /*if (!$server->hasSetting('impero.service.ssh.allowRootLogin')) {
            $allowRootLogin = true;
            SettingsMorph::makeItHappen('impero.service.ssh.sshPort', $allowRootLogin, Servers::class, $server->id);
            $inSync = false;
        } else {
            $allowRootLogin = $server->getSettingValue('impero.service.ssh.allowRootLogin');
        }*/

        if (!$inSync) {
            Task::create('Syncing SSH settings')->make(function() use ($settings, $server) {
                /**
                 * We will edit /etc/ssh/sshd_config
                 * Find line which starts with 'Port '
                 * Replace it with 'Port $newVal'
                 *
                 */
            });
        }
    }

}