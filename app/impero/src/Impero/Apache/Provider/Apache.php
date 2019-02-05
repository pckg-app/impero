<?php namespace Impero\Apache\Provider;

use Impero\Apache\Console\ApacheGraceful;
use Impero\Apache\Console\DumpVirtualhosts;
use Impero\Apache\Console\LetsEncryptRenew;
use Impero\Apache\Console\RestartApache;
use Impero\Apache\Controller\Apache as ApacheController;
use Impero\Controller\Impero;
use Impero\Servers\Resolver\Server;
use Impero\Sites\Controller\Sites;
use Impero\Sites\Resolver\Site as SiteResolver;
use Pckg\Framework\Provider;
use Pckg\Framework\Router\Route\Group;
use Pckg\Framework\Router\Route\Route;

class Apache extends Provider
{

    public function routes()
    {
        return [
            'url' => maestro_urls(ApacheController::class, 'apache', 'site', SiteResolver::class, 'apache/sites'),

            (new Group([
                           'controller' => Sites::class,
                           'urlPrefix'  => '/api/site',
                           'namePrefix' => 'api.site',
                           'tags'       => ['auth:in'],
                       ]))->routes([
                                       '.cronjob'    => (new Route('/[site]/cronjob', 'cronjob'))->resolvers([
                                                                                                                 'site' => SiteResolver::class,
                                                                                                             ]),
                                       '.cronjobs'   => (new Route('/[site]/cronjobs', 'cronjobs'))->resolvers([
                                                                                                                   'site' => SiteResolver::class,
                                                                                                               ]),
                                       '.mysqlSlave' => (new Route('/[site]/mysql-slave', 'mysqlSlave'))->resolvers([
                                                                                                                        'site'   => SiteResolver::class,
                                                                                                                        'server' => (new Server())->fromPost('server'),
                                                                                                                    ]),
                                       '.checkout'   => (new Route('/[site]/checkout', 'checkout'))->resolvers([
                                                                                                                   'site' => SiteResolver::class,
                                                                                                               ]),
                                       '.recheckout' => (new Route('/[site]/recheckout', 'recheckout'))->resolvers([
                                                                                                                       'site' => SiteResolver::class,
                                                                                                                   ]),
                                       '.deploy'     => (new Route('/[site]/deploy', 'deploy'))->resolvers([
                                                                                                               'site' => SiteResolver::class,
                                                                                                           ]),
                                       '.check'      => (new Route('/[site]/check', 'check'))->resolvers([
                                                                                                             'site' => SiteResolver::class,
                                                                                                         ]),
                                   ]),
        ];
    }

    public function consoles()
    {
        return [
            DumpVirtualhosts::class,
            RestartApache::class,
            ApacheGraceful::class,
            LetsEncryptRenew::class,
        ];
    }

}