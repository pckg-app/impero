<?php namespace Impero\Sites\Provider;

use Impero\Sites\Controller\Sites as SitesController;
use Impero\Sites\Resolver\Site;
use Pckg\Framework\Provider;
use Pckg\Framework\Router\Route\Group;
use Pckg\Framework\Router\Route\Route;

class Sites extends Provider
{
    public function routes()
    {
        return [
            routeGroup(
                [
                    'controller' => SitesController::class,
                    'urlPrefix'  => '/site',
                    'namePrefix' => 'impero.site',
                    'tags'       => ['auth:in'],
                ],
                [
                    '.confirmDelete' => route('/[site]/confirm-delete', 'confirmDelete')
                        ->resolvers(['site' => Site::class]),
                ]
            ),
            (new Group(
                [
                    'controller' => SitesController::class,
                    'urlPrefix'  => '/api/site',
                    'namePrefix' => 'api.impero.site',
                    'tags'       => ['auth:in'],
                ]
            ))->routes(
                [
                    '.create'         => (new Route('', 'create')),
                    ''                => (new Route('/[site]', 'site'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.exec'           => (new Route('/[site]/exec', 'exec'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.createFile'     => (new Route('/[site]/createFile', 'createFile'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.setDomain'      => (new Route('/[site]/set-domain', 'setDomain'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.letsencrypt'    => (new Route('/[site]/letsencrypt', 'letsencrypt'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.hasSiteDir'     => (new Route('/[site]/has-site-dir', 'hasSiteDir'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.hasRootDir'     => (new Route('/[site]/has-root-dir', 'hasRootDir'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.hasSiteSymlink' => (new Route('/[site]/has-site-symlink', 'hasSiteSymlink'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.hasSiteFile'    => (new Route('/[site]/has-site-file', 'hasSiteFile'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.infrastructure' => (new Route('/[site]/infrastructure', 'infrastructure'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.changeVariable' => (new Route('/[site]/change-variable', 'changeVariable'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.changePckg' => (new Route('/[site]/change-pckg', 'changePckg'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.vars' => (new Route('/[site]/vars', 'vars'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                    '.fileContent' => (new Route('/[site]/file-content', 'fileContent'))
                        ->resolvers(
                            [
                                'site' => Site::class,
                            ]
                        ),
                ]
            ),
        ];
    }
}