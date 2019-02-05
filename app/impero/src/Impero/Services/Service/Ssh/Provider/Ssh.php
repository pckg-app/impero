<?php namespace Impero\Services\Service\Ssh\Provider;

use Impero\Servers\Resolver\Server;
use Impero\Services\Service\Ssh\Controller\Ssh as SshController;
use Pckg\Framework\Provider;

class Ssh extends Provider
{

    public function routes()
    {
        return [
            routeGroup([
                           'controller' => SshController::class,
                           'namePrefix' => 'api.services.ssh',
                           'urlPrefix'  => '/api/services/ssh',
                       ], [
                           '.server.settings' => route('/server/[server]/settings', 'serverSettings')->resolvers([
                                                                                                                     'server' => Server::class,
                                                                                                                 ]),
                       ]),
        ];
    }

}