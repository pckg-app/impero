<?php namespace Impero\Services\Service;

/**
 * Class Openvpn
 *
 * @package Impero\Services\Service
 */
class Openvpn extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'openvpn';

    /**
     * @var string
     */
    protected $name = 'OpenVPN';

    /**
     * @return bool|mixed|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()->exec('openvnp --version');

        return $response;
        $start = strpos($response, 'OpenVPN ') + strlen('OpenVPN ');
        $end = strpos($response, " ", $start);
        $length = $end - $start;

        return substr($response, $start, $length);
    }

}