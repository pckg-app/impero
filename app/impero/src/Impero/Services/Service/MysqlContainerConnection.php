<?php namespace Impero\Services\Service;

use Impero\Services\Service\Connection\ContainerConnection;
use Impero\Services\Service\Connection\SshConnection;

class MysqlContainerConnection extends MysqlConnection
{

    public function __construct(ContainerConnection $sshConnection)
    {
        $this->sshConnection = $sshConnection;
    }

}