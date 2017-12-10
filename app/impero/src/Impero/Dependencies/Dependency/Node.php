<?php namespace Impero\Dependencies\Dependency;

class Node extends AbstractDependency
{

    protected $dependency = 'node';

    protected $dependencies = [
        Curl::class,
    ];

    public function getVersion()
    {
        $response = $this->getConnection()
                         ->exec('node --version');

        return $response;
    }

    public function getStatus()
    {
        $outdated = false;

        return $outdated
            ? 'outdated'
            : 'ok';
    }

    public function install()
    {
        $commands = [
            'curl -sL https://deb.nodesource.com/setup_8.x',
            'apt-get install nodejs -y',
            'ln -s /usr/bin/nodejs /usr/bin/node',
        ];
        $connection = $this->getConnection();
        foreach ($commands as $command) {
            $connection->exec($command);
        }
    }

}