<?php namespace Impero\Services\Service;

/**
 * Class Ufw
 *
 * @package Impero\Services\Service
 */
class Ufw extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'ufw';

    /**
     * @var string
     */
    protected $name = 'UFW';

    /**
     * @return bool|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()->exec('ufw version');

        $start = strpos($response, 'ufw ') + strlen('ufw ');
        $end = strpos($response, "\n");
        $length = $end - $start;

        return substr($response, $start, $length);
    }

}