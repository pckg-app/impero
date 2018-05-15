<?php namespace Impero\Servers\Service;

use Impero\Servers\Record\Server;
use Impero\Services\Service\Connection\ConnectionInterface;
use Impero\Services\Service\Connection\LocalConnection;
use Impero\Services\Service\Connection\SshConnection;

class ConnectionManager
{

    protected $connections = [];

    /**
     * @param Server $server
     *
     * @return ConnectionInterface
     * @throws \Exception
     */
    public function createConnection(Server $server = null)
    {
        /**
         * When server is not passed, create local connection.
         */
        if (!$server && !isset($this->connections[null])) {
            return $this->connections[null] = new LocalConnection();
        }

        /**
         * When server is passed, create remote ssh connection.
         */
        if ($server && !isset($this->connections[$server->id])) {
            return $this->connections[$server->id] = new SshConnection(
                $server, $server->ip, $server->user, $server->port,
                path('storage') . 'private/keys/id_rsa_' . $server->id
            );
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