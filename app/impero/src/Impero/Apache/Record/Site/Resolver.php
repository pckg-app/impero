<?php namespace Impero\Apache\Record\Site;

use Impero\Apache\Entity\Sites;
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

    public function __construct(Router $router, Auth $auth, Sites $sites, Response $response)
    {
        $this->router = $router;
        $this->auth = $auth;
        $this->sites = $sites;
        $this->response = $response;
    }

    public function resolve($class)
    {
        if (!$id = $this->router->get('site')) {
            $this->response->bad('Site parameter is missing ...');
        }

        return $this->sites->where('id', $id)//->userIsAuthorized()
                           ->oneOrFail(function() {
            $this->response->unauthorized('Site not found');
        });
    }

    public function parametrize($record)
    {
        return $record->id;
    }

}
