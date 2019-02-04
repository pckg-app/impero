<?php namespace Impero\Services\Service;

/**
 * Class Apache
 *
 * @package Impero\Services\Service
 */
class Apache extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'apache2';

    /**
     * @var string
     */
    protected $name = 'Apache';

    /**
     * @return bool|mixed|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()->exec('apache2 -v');

        $start = strpos($response, 'Server version: ') + strlen('Server version: ');
        $end = strpos($response, "\n");
        $length = $end - $start;

        return substr($response, $start, $length);
    }

}