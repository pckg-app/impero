<?php namespace Impero\Servers\Service;

use Impero\Servers\Record\Server;
use Impero\Services\Service\SshConnection;

class ConnectionManager
{

    protected $connections = [];

    public function createConnection(Server $server)
    {
        if (!isset($this->connections[$server->id])) {
            $this->connections[$server->id] = new SshConnection($server, $server->ip, $server->user, $server->port,
                                                                path('storage') . 'private/keys/id_rsa_' . $server->id);
        }

        return $this->connections[$server->id];
    }

    public function __destruct()
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
    }

}