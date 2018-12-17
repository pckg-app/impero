<?php namespace Impero\Servers\Provider;

use Impero\Servers\Controller\Servers as ServersController;
use Impero\Servers\Resolver\Server;
use Impero\Sites\Controller\Sites;
use Impero\Sites\Resolver\Site;
use Pckg\Framework\Provider;
use Pckg\Framework\Router\Route\Group;
use Pckg\Framework\Router\Route\Route;
use Pckg\Generic\Middleware\EncapsulateResponse;

class Servers extends Provider
{

    public function routes()
    {
        return [
            /**
             * Frontend routes.
             */
            (new Group([
                           'controller' => ServersController::class,
                           'urlPrefix'  => '/servers',
                           'namePrefix' => 'impero.servers',
                           'tags' => ['auth:in'],
                       ]))->routes([
                                       ''                                => new Route('', 'index'),
                                       '.server'                         => (new Route('/server/[server]',
                                                                                       'viewServer'))->resolvers([
                                                                                                                     'server' => Server::class,
                                                                                                                 ]),
                                       '.addServer'                      => new Route('/add', 'addServer'),
                                       '.refreshServersServiceStatus'    => new Route('/servers-service/[serversService]/refresh',
                                                                                      'refreshServersServiceStatus'),
                                       '.refreshServersDependencyStatus' => new Route('/servers-dependency/[serversDependency]/refresh',
                                                                                      'refreshServersDependencyStatus'),
                                   ]),
            /**
             * Webhook
             */
            (new Group([
                           'controller' => ServersController::class,
                       ]))->routes([
                                       'webhook' => new Route('/webhook', 'webhook'),
                                   ]),
            /**
             * Installer
             */
            (new Group([
                           'controller' => ServersController::class,
                       ]))->routes([
                                       'installer'   => (new Route('/install.sh', 'installSh'))->data([
                                                                                                          'tags' => [
                                                                                                              EncapsulateResponse::class .
                                                                                                              '.disable',
                                                                                                          ],
                                                                                                      ]),
                                       'postInstall' => (new Route('/postinstall', 'installNewServer')),
                                   ]),
            /**
             * API routes.
             */
            (new Group([
                           'controller' => ServersController::class,
                           'urlPrefix'  => '/api/servers',
                           'namePrefix' => 'api.servers',
                       ]))->routes([
                                       ''                 => new Route('', 'servers'),
                                       '.server'          => (new Route('/[server]', 'server'))->resolvers([
                                                                                                               'server' => Server::class,
                                                                                                           ]),
                                       '.server.services' => (new Route('/[server]/services', 'serverServices'))->resolvers([
                                                                                                                                'server' => Server::class,
                                                                                                                            ]),
                                       '.server.dependencies' => (new Route('/[server]/dependencies', 'serverDependencies'))->resolvers([
                                                                                                                                'server' => Server::class,
                                                                                                                            ]),
                                       '.server.websites' => (new Route('/[server]/websites', 'serverWebsites'))->resolvers([
                                                                                                                                'server' => Server::class,
                                                                                                                            ]),
                                       '.server.networkInterfaces' => (new Route('/[server]/network-interfaces', 'networkInterfaces'))->resolvers([
                                                                                                                                'server' => Server::class,
                                                                                                                            ]),
                                       '.server.firewallSettings' => (new Route('/[server]/firewall-settings', 'firewallSettings'))->resolvers([
                                                                                                                                'server' => Server::class,
                                                                                                                            ]),
                                       '.server.connect'  => (new Route('/[server]/connect', 'connect'))->resolvers([
                                                                                                                        'server' => Server::class,
                                                                                                                    ]),
                                   ]),
            (new Group([
                           'controller' => ServersController::class,
                           'urlPrefix'  => '/api/server',
                           'namePrefix' => 'api.server',
                           'tags' => ['auth:in'],
                       ]))->routes([
                                       '.cronjob' => (new Route('/[server]/cronjob', 'cronjob'))->resolvers([
                                                                                                                'server' => Server::class,
                                                                                                            ]),
                                   ]),
        ];
    }
}