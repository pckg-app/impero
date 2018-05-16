<?php namespace Impero\Services\Service;

/**
 * Class GNUPG2
 *
 * @package Impero\Services\Service
 */
class GNUPG2 extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'gnupg2';

    /**
     * @var string
     */
    protected $name = 'gpg2';

    /**
     * @return bool|mixed|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()
                         ->exec('ufw version');

        $start = strpos($response, 'ufw ') + strlen('ufw ');
        $end = strpos($response, "\n");
        $length = $end - $start;

        return substr($response, $start, $length);
    }

}