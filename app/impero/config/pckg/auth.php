<?php

use Pckg\Auth\Service\Auth;

return [
    /**
     * HTTP header used for API authentication.
     */
    'apiHeader' => 'X-Impero-Api-Key',
    /**
     * Authentication providers.
     * We will use default Pckg DB provider here.
     */
    'providers' => [
        'frontend' => [
            'type' => \Pckg\Auth\Service\Provider\Database::class,
            'entity' => \Pckg\Auth\Entity\Users::class,
            'hash' => '', // fill this in env.php on each server
            'version' => 'secure',
            'forgotPassword' => true,
            'userGroup' => 'status_id',
        ],
    ],
    /**
     * User groups.
     */
    'groups' => [
        1 => [
            'title' => 'Administrator',
        ],
        2 => [
            'title' => 'User',
        ],
    ],
    /**
     * Tags
     */
    'tags' => [
        'auth:in' => function (Auth $auth) {
            return $auth->isLoggedIn();
        },
        'auth:out' => function (Auth $auth) {
            return !$auth->isLoggedIn();
        },
        'group:admin' => function (Auth $auth) {
            return $auth->isAdmin();
        },
    ],
    /**
     * Gates
     */
    'gates' => [
        [
            'provider' => 'frontend',
            'tags' => ['auth:in'],
            'internal' => '/login',
        ],
        [
            'provider' => 'frontend',
            'tags' => ['auth:out'],
            'internal' => '/',
        ],
        [
            'provider' => 'frontend',
            'tags' => ['group:admin'],
            'internal' => '/',
        ],
    ]
];