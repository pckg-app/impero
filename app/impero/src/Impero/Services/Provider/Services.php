<?php namespace Impero\Services\Provider;

use Impero\Servers\Resolver\Server;
use Impero\Services\Controller\Services as ServicesController;
use Impero\Services\Resolver\Service;
use Pckg\Framework\Provider;

class Services extends Provider
{

    public function routes()
    {
        return [
            routeGroup([
                           'controller' => ServicesController::class,
                           'urlPrefix'  => '/api/services',
                           'namePrefix' => 'api.services',
                       ], [
                           ''         => route('', 'services'),
                           '.install' => route('/[service]/install/[server]', 'install')->resolvers([
                                                                                                        'server'  => Server::class,
                                                                                                        'service' => Service::class,
                                                                                                    ]),
                       ]),
        ];
    }

}