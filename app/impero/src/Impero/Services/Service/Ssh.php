<?php namespace Impero\Services\Service;

/**
 * Class Ssh
 *
 * @package Impero\Services\Service
 */
class Ssh extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'ssh';

    /**
     * @var string
     */
    protected $name = 'SSH';

    /**
     * @return bool|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()
                         ->exec('ssh -V');

        $length = strpos($response, ",");

        return substr($response, 0, $length);
    }

}