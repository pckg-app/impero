<?php namespace Impero\Services\Service;

use Defuse\Crypto\Key;
use Impero\Services\Service\Connection\Connectable;
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

    public function __construct(Connectable $connection)
    {
        $this->connection = $connection->getConnection();
    }

    /**
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    protected function prepareRandomFile()
    {
        return sha1(Key::createNewRandomKey()->saveToAsciiSafeString());
    }

    protected function prepareDirectory($dir)
    {
        $dir = '/home/impero/.impero/service/' . $dir;

        if ($this->getConnection()->dirExists($dir)) {
            return $dir;
        }

        $this->getConnection()->exec('mkdir -p ' . $dir);

        return $dir;
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