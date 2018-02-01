<?php namespace Impero\Apache\Provider;

use Impero\Apache\Console\ApacheGraceful;
use Impero\Apache\Console\DumpVirtualhosts;
use Impero\Apache\Console\LetsEncryptRenew;
use Impero\Apache\Console\RestartApache;
use Impero\Apache\Controller\Apache as ApacheController;
use Impero\Apache\Record\Site\Resolver as SiteResolver;
use Impero\Controller\Impero;
use Impero\Sites\Controller\Sites;
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
                           'namePrefix' => 'api.impero.site',
                       ]))->routes([
                                       '.cronjob'  => (new Route('/[site]/cronjob', 'cronjob'))
                                           ->resolvers([
                                                           'site' => SiteResolver::class,
                                                       ]),
                                       '.redeploy' => (new Route('/[site]/redeploy', 'redeploy'))
                                           ->resolvers([
                                                           'site' => SiteResolver::class,
                                                       ]),
                                       '.deploy' => (new Route('/[site]/deploy', 'deploy'))
                                           ->resolvers([
                                                           'site' => SiteResolver::class,
                                                       ]),
                                       '.check'    => (new Route('/[site]/check', 'check'))
                                           ->resolvers([
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