<?php namespace Impero\Services\Service;

/**
 * Class Nginx
 *
 * @package Impero\Services\Service
 */
class Nginx extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'ngingx';

    /**
     * @var string
     */
    protected $name = 'Nginx';

    /**
     * @return bool|mixed|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()->exec('nginx version');

        $start = strpos($response, 'ufw ') + strlen('ufw ');
        $end = strpos($response, "\n");
        $length = $end - $start;

        return substr($response, $start, $length);
    }

}