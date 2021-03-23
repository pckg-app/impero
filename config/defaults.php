<?php

return [
    'app'      => null,
    'domain'   => null,
    'title'    => null,
    'protocol' => 'http',
    'security' => [
        'hash'   => 'njbtlnroq4f27myupt5rqnf3kbm2ccb2rvb3egwl5e3ua',
        'dbhash' => '7mc0pyl80adssdfgc60tz0rbdihpd4gipfxkbhj1yas',
        'key' => dotenv('SECURITY_KEY'),
    ],
    'twig'     => [
        'cache'   => # requires composer doctrine/cache
            [
                'driver' => 'Cache\Lib\Doctrine\Common\Cache\PhpFileCache',
            ],
        'session' => [
            'enable' => true,
        ],
        'i18n'    => [
            'type'    => 'session',
            'default' => 'en_GB',
            'force'   => false,
            'langs'   => [
                'en_GB' => [
                    'id'   => 1,
                    'code' => 'en',
                ],
                'sl_SI' => [
                    'id'   => 2,
                    'code' => 'sl',
                ],
            ],
        ],
    ],
    'lessc' => ' --js=true',
];
