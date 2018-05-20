<?php

return [
    'gates'     => [
        [
            'provider' => 'frontend',
            'tags'     => ['auth:in'],
            'redirect' => 'login',
        ],
    ],
    'tags'      => [
        'auth:in' => function() {
            return auth()->isLoggedIn();
        },
    ],
    'providers' => [
        'frontend' => [
            'type'           => \Pckg\Auth\Service\Provider\Database::class,
            'entity'         => \Pckg\Auth\Entity\Users::class,
            'hash'           => '', // fill this in env.php on each server
            'version'        => 'secure',
            'forgotPassword' => true,
            'userGroup'      => 'status_id',
        ],
    ],
    'apiHeader' => 'X-Impero-Api-Key',
    // 'appEntity' => \Center\Entity\AppKeys::class,
];