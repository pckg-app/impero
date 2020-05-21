<?php namespace Impero\Services\Service\Connection;

class ContainerConnection implements ConnectionInterface, Connectable
{

    /**
     * @var SshConnection
     */
    protected $sshConnection;

    /**
     * @var string
     */
    protected $container;

    /**
     * @var
     */
    protected $connection;

    /**
     * ContainerConnection constructor.
     * @param SshConnection|ConnectionInterface|Connectable $sshConnection
     * @param string $container
     */
    public function __construct(SshConnection $sshConnection, string $container)
    {
        $this->sshConnection = $sshConnection;
        $this->container = $container;
    }

    /**
     * @return Connectable|ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Open connection to the container.
     * Nothing is done here. Every command is executed per-se.
     *
     * @return mixed|void
     */
    public function open()
    {
    }

    public function close()
    {
    }

    /**
     * Execute command on docker container.
     *
     * @param $command
     * @param null $output
     * @param null $error
     * @return mixed|void
     */
    public function exec($command, &$output = null, &$error = null)
    {
        $fullCommand = 'docker exec -i ' . $this->container . ' ' . $command;

        return $this->sshConnection->exec($fullCommand, $output, $error);
    }

    public function dirExists($dir)
    {
        throw new \Exception('ContainerConnection::dirExists is not implemented');
    }

    public function createDir($dir, $mode, $recursive)
    {
        throw new \Exception('ContainerConnection::createDir is not implemented');
    }

    public function saveContent($file, $content)
    {
        throw new \Exception('ContainerConnection::saveContent is not implemented');
    }


}