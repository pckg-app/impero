<?php namespace Impero\Services\Service;

/**
 * Class HAProxy
 *
 * @package Impero\Services\Service
 */
class HAProxy extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'haproxy';

    /**
     * @var string
     */
    protected $name = 'HAProxy';

    public function getCandidates()
    {
        return [
            'ubuntu16.04' => [
                'default'                 => '1.6.3',
                // software-properties-common -> 4 add-apt-repository command
                // sudo add-apt-repository ppa:vbernat/haproxy-1.8 && sudo apt-get update && sudo apt-get install --only-upgrade haproxy
                'ppa:vbernat/haproxy-1.8' => '1.8.2',
            ],
        ];
    }

    /**
     * @return bool|mixed|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()
                         ->exec('haproxy -v');

        $start = strpos($response, 'HA-Proxy version ') + strlen('HA-Proxy version ');
        $end = strpos($response, "\n");
        $length = $end - $start;

        return substr($response, $start, $length);
    }

    public function reload()
    {
        $command = 'sudo service haproxy reload';
        $this->exec($command);
    }

    public function restart()
    {
        $command = 'sudo service haproxy restart';
        $this->exec($command);
    }

    public function reloadSocket()
    {
        $command = 'sudo haproxy -f /etc/haproxy/haproxy.cfg -p /var/run/haproxy.pid -sf $(cat /var/run/haproxy.pid)';
        $this->exec($command);
    }

}