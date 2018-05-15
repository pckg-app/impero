<?php namespace Impero\Services\Service;

use Impero\Services\Service\Connection\ConnectionInterface;

class AbstractService
{

    /**
     * @var ConnectionInterface
     */
    protected $connection;

    protected $service;

    protected $name;

    protected $via = 'apt';

    protected $install;

    protected $dependencies = [];

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    public function getName()
    {
        return $this->name;
    }

    public function exec($command, &$output = null, &$error = null)
    {
        return $this->getConnection()->exec($command, $output, $error);
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function isInstalled()
    {
        $response = $this->getConnection()
                         ->exec('service ' . $this->service . ' status');

        $notFound = strpos($response, 'Loaded: not-found');
        $loaded = strpos($response, 'Loaded: loaded');

        return $loaded && !$notFound;
    }

    public function getStatus()
    {
        $response = $this->getConnection()
                         ->exec('service ' . $this->service . ' status');

        $loaded = strpos($response, 'Loaded: loaded');
        $active = strpos($response, 'Active: active (running)');
        $exited = strpos($response, 'Active: active (exited)');
        $notFound = strpos($response, 'Loaded: not-found');

        return $loaded
            ? ($active
                ? 'ok'
                : ($exited
                    ? 'ok, exited'
                    : 'inactive'))
            : ($notFound
                ? 'missing'
                : 'error');
    }

    public function install()
    {
        if ($this->via == 'apt') {
            $this->getConnection()->exec('sudo apt-get install -y ' . ($this->install ?? $this->service));
        } elseif ($this->via == 'npm') {
            $this->getConnection()->exec('sudo npm install -g ' . ($this->install ?? $this->service));
        }
    }

}