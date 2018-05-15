<?php namespace Impero\Services\Record;

use Exception;
use Impero\Services\Entity\Services;
use Impero\Services\Service\Apache;
use Impero\Services\Service\Cron;
use Impero\Services\Service\Mysql;
use Impero\Services\Service\Nginx;
use Impero\Services\Service\Openvpn;
use Impero\Services\Service\Php;
use Impero\Services\Service\Pureftpd;
use Impero\Services\Service\Sendmail;
use Impero\Services\Service\Ssh;
use Impero\Services\Service\Connection\SshConnection;
use Impero\Services\Service\Ufw;
use Pckg\Concept\Reflect;
use Pckg\Database\Record;

class Service extends Record
{

    protected $entity = Services::class;

    protected $toArray = ['pivot'];

    protected $handlers = [
        'apache2'  => Apache::class,
        'ssh'      => Ssh::class,
        'mysql'    => Mysql::class,
        'ufw'      => Ufw::class,
        'php'      => Php::class,
        'nginx'    => Nginx::class,
        'cron'     => Cron::class,
        'openvpn'  => Openvpn::class,
        'sendmail' => Sendmail::class,
        'pureftpd' => Pureftpd::class,
    ];

    public function getHandler(SshConnection $connection)
    {
        $handlerClass = $this->handlers[$this->service] ?? null;

        if (!$handlerClass) {
            throw new Exception('Handler for service ' . $this->name . ' not found!');
        }

        return Reflect::create($handlerClass, [$connection]);
    }

}