<?php namespace Impero\Services\Service;

use Impero\Jobs\Record\Job;
use Impero\Services\Service\Connection\ContainerConnection;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Docker
 *
 * @package Impero\Services\Service
 */
class Docker extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'docker';

    /**
     * @var string
     */
    protected $name = 'Docker';

    /**
     * @return bool|mixed|string
     */
    public function getVersion()
    {
        // docker --version
    }

    public function activate()
    {
        /**
         * Install docker.
         */
        $this->getConnection()->exec('sudo apt-get install -y apt-transport-https ca-certificates curl gnupg-agent software-properties-common');
        $this->getConnection()->exec('curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo apt-key add -');
        $this->getConnection()->exec('sudo add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable"');

        $this->getConnection()->exec('sudo apt-get update && sudo apt-get install -y docker-ce docker-ce-cli containerd.io');

        /**
         * Add docker privileges to impero user.
         */
        $this->getConnection()->exec('sudo groupadd docker');
        $this->getConnection()->exec('sudo usermod -aG docker impero', $output, $error);

        /**
         * Activate docker service when server is restarted.
         */
        $this->getConnection()->exec('sudo systemctl enable --now docker', $output, $error);
    }

    public function initSwarm($ip)
    {
        $this->getConnection()->exec('sudo docker swarm init --advertise-addr ' . $ip);
    }

    public function deploySwarm($name, $dir, $files, $variables = [])
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        /**
         * Build compose files.
         */
        $entrypoints = collect($files)->map(function ($content, $file) {
            return '-c ' . $file;
        })->implode(' ');

        /**
         * Build env vars.
         */
        $vars = collect($variables)->map(function ($val, $key) {
            return 'env ' . $key . '=' . escapeshellarg($val);
        })->implode(' ');

        /**
         * Build commend.
         */
        $command = 'cd ' . $dir . ' && ' . $vars . ' docker stack deploy ' . $name . ' ' . $entrypoints . ' --with-registry-auth --prune --resolve-image';

        /**
         * Dump entrypoints.
         */
        foreach ($files as $file => $content) {
            $this->getConnection()->saveContent($dir . $file, $content);
        }

        /**
         * This is also where we need to parse all $files and services for env_file.
         * Every env_file should be generated and populated here.
         */
        $this->getConnection()->exec($command);
    }

    /**
     * @param $swarmName
     * @param $serviceName
     * @return ContainerConnection
     */
    public function getContainerConnection($swarmName, $serviceName)
    {
        $container = $this->getContainerId($swarmName, $serviceName);

        return new ContainerConnection($this->getConnection(), $container);
    }

    /**
     * @param $swarmName
     * @param $serviceName
     * @return mixed|string
     */
    public function getContainerId($swarmName, $serviceName)
    {
        $command = 'docker ps | grep "' . $swarmName . '_' . $serviceName . '"';
        $this->getConnection()->exec($command, $output);

        return explode(' ', $output[0])[0]; // get container id
    }

}