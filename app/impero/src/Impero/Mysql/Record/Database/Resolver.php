<?php namespace Impero\Mysql\Record\Database;

use Impero\Apache\Entity\Sites;
use Impero\Mysql\Entity\Databases;
use Pckg\Auth\Service\Auth;
use Pckg\Concept\Reflect\Resolver as ResolverInterface;
use Pckg\Framework\Response;
use Pckg\Framework\Router;

class Resolver implements ResolverInterface
{

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var Auth
     */
    protected $auth;

    /**
     * @var Sites
     */
    protected $sites;

    /**
     * @var Response
     */
    protected $response;

    public function __construct(Router $router, Auth $auth, Databases $databases, Response $response)
    {
        $this->router = $router;
        $this->auth = $auth;
        $this->databases = $databases;
        $this->response = $response;
    }

    public function resolve($class)
    {
        if (!$id = $this->router->get('database')) {
            $this->response->bad('Database parameter is missing ...');
        }

        return $this->databases->where('id', $id)//->userIsAuthorized()
                               ->oneOrFail(function() {
                $this->response->unauthorized('Database not found');
            });
    }

    public function parametrize($record)
    {
        return $record->id;
    }

}
