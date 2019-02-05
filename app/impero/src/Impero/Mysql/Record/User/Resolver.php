<?php namespace Impero\Mysql\Record\User;

use Impero\Mysql\Entity\Users;
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
     * @var Users
     */
    protected $users;

    /**
     * @var Response
     */
    protected $response;

    public function __construct(Router $router, Auth $auth, Users $users, Response $response)
    {
        $this->router = $router;
        $this->auth = $auth;
        $this->users = $users;
        $this->response = $response;
    }

    public function resolve($class)
    {
        if (!$id = $this->router->get('user')) {
            $this->response->bad('User parameter is missing ...');
        }

        return $this->users->where('id', $id)//->userIsAuthorized()
                           ->oneOrFail(function() {
            $this->response->unauthorized('User not found');
        });
    }

    public function parametrize($record)
    {
        return $record->id;
    }

}
