<?php

return [
    'default' => [
        'driver'  => 'mysql',
        'host'    => dotenv('DB_HOST', 'localhost'),
        'user'    => dotenv('DB_USER', 'user'),
        'pass'    => dotenv('DB_PASS', 'pass'),
        'db'      => 'pckg_impero',
        'charset' => 'utf8',
    ],
    'dynamic' => [
        'driver'  => 'mysql',
        'host'    => dotenv('DB_HOST', 'localhost'),
        'user'    => dotenv('DB_USER', 'user'),
        'pass'    => dotenv('DB_PASS', 'pass'),
        'db'      => 'pckg_impero',
        'charset' => 'utf8',
    ],
    /*'pureftpd' => [
        'driver'  => 'mysql',
        'host'    => '127.0.0.1',
        'user'    => 'pckg_impero',
        'pass'    => 'pckg_impero',
        'db'      => 'pureftpd',
        'charset' => 'utf8',
    ],*/
];
