<?php

use Impero\Services\Service;

return [
    'apache'                     => [
        'title'   => 'Apache2',
        'service' => Service\Apache::class,
    ],
    'ssh'                        => [
        'title'   => 'SSH',
        'service' => Service\Ssh::class,
    ],
    'mysql'                      => [
        'title'   => 'MySQL',
        'service' => Service\Mysql::class,
    ],
    'ufw'                        => [
        'title'   => 'UFW',
        'service' => Service\Ufw::class,
    ],
    'php'                        => [
        'title'   => 'PHP',
        'service' => Service\Php::class,
    ],
    'nginx'                      => [
        'title'   => 'NginX',
        'service' => Service\Nginx::class,
    ],
    'cron'                       => [
        'title'   => 'Cron',
        'service' => Service\Cron::class,
    ],
    'openvpn'                    => [
        'title'   => 'OpenVPN',
        'service' => Service\Openvpn::class,
    ],
    'openssl'                    => [
        'title'   => 'OpenSSL',
        'service' => Service\OpenSSL::class,
    ],
    'gpg'                        => [
        'title'   => 'GPG',
        'service' => Service\GPG::class,
    ],
    'gpg2'                       => [
        'title' => 'GPG v2',
    ],
    'haproxy'                    => [
        'title'   => 'HAProxy',
        'service' => Service\HAProxy::class,
    ],
    'zip'                        => [
        'title'   => 'ZIP',
        'service' => Service\HAProxy::class,
    ],
    'sendmail'                   => [
        'title'   => 'Sendmail',
        'service' => Service\HAProxy::class,
    ],
    'lsyncd'                     => [
        'title'   => 'Lsyncd',
        'service' => Service\HAProxy::class,
    ],
    'pureftpd'                   => [
        'title'   => 'PureFTPd',
        'service' => Service\HAProxy::class,
    ],
    'locales'                    => [
        'title'   => 'Locales',
        'service' => Service\Locales::class,
    ],
    'software-properties-common' => [
        'title'   => 'Software properties (common)',
        'service' => Service\SoftwareProperties::class,
    ],
];