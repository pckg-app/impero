<?php namespace Impero\Dependencies\Dependency;

use Impero\Services\Service\Connection\SshConnection;

abstract class AbstractDependency implements DependencyInterface
{

    /**
     * @var SshConnection
     */
    protected $connection;

    protected $dependency;

    protected $name;

    protected $dependencies = [];

    protected $via = [];

    public function __construct(SshConnection $connection)
    {
        $this->connection = $connection;
    }

    public function getName()
    {
        return $this->name;
    }

    public function isInstalled()
    {
        $this->getConnection()->exec($this->dependency, $error);

        if ($error && strpos($error, 'command not found')) {
            return false;
        }

        return true;
    }

    public function getConnection()
    {
        return $this->connection;
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