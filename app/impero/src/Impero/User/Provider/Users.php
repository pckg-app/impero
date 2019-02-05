<?php namespace Impero\User\Provider;

use Impero\User\Controller\Users as UsersController;
use Impero\User\Resolver\User;
use Pckg\Auth\Provider\ApiAuth;
use Pckg\Framework\Provider;

class Users extends Provider
{

    public function routes()
    {
        return [
            routeGroup([
                           'urlPrefix'  => '/api/',
                           'namePrefix' => 'api',
                           'controller' => UsersController::class,
                           'tags'       => ['auth:in'],
                       ], [
                           '.user.create' => route('user', 'create'),
                           '.user'        => route('user/[user]', 'user')->resolvers(['user' => User::class]),
                       ]),
        ];
    }

    public function providers()
    {
        return [
            ApiAuth::class,
        ];
    }

}