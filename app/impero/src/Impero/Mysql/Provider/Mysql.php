<?php namespace Impero\Mysql\Provider;

use Impero\Mysql\Controller\Database;
use Impero\Mysql\Controller\DatabaseApi;
use Impero\Mysql\Controller\DatabaseUserApi;
use Impero\Mysql\Controller\User;
use Impero\Mysql\Record\Database\Resolver as DatabaseResolver;
use Impero\Mysql\Record\User\Resolver as UserResolver;
use Impero\Mysql\Resolver\Database as NewDatabaseResolver;
use Pckg\Framework\Provider;
use Pckg\Framework\Router\Route\Group;
use Pckg\Framework\Router\Route\Route;

class Mysql extends Provider
{

    public function routes()
    {
        return [
            'url' => maestro_urls(Database::class, 'database', 'database', DatabaseResolver::class, 'mysql/databases') +
                maestro_urls(User::class, 'user', 'user', UserResolver::class, 'mysql/users'),

            (new Group([
                           'controller' => DatabaseApi::class,
                           'urlPrefix'  => '/api/database',
                           'namePrefix' => 'api.impero.database',
                       ]))->routes([
                                       ''            => (new Route('', 'database')),
                                       '.search'     => (new Route('/search', 'search')),
                                       '.query'      => (new Route('/[database]/query', 'query'))->resolvers([
                                                                                                                 'database' => NewDatabaseResolver::class,
                                                                                                             ]),
                                       '.importFile' => (new Route('/[database]/importFile', 'importFile'))->resolvers([
                                                                                                                           'database' => NewDatabaseResolver::class,
                                                                                                                       ]),
                                       '.replicate'  => (new Route('/[database]/replicate', 'replicate'))->resolvers([
                                                                                                                         'database' => NewDatabaseResolver::class,
                                                                                                                     ]),
                                       '.backup'     => (new Route('/[database]/backup', 'backup'))->resolvers([
                                                                                                                   'database' => NewDatabaseResolver::class,
                                                                                                               ]),
                                   ]),
            (new Group([
                           'controller' => DatabaseUserApi::class,
                           'urlPrefix'  => '/api/databaseUser',
                           'namePrefix' => 'api.impero.databaseUser',
                       ]))->routes([
                                       '' => (new Route('', 'user'))->resolvers(),
                                   ]),
        ];
    }

}