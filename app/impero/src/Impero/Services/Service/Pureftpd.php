<?php namespace Impero\Services\Service;

/**
 * Class Pureftpd
 *
 * @package Impero\Services\Service
 */
class Pureftpd extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'pure-ftpd-mysql';

    /**
     * @var string
     */
    protected $name = 'PureFTPd';

    /**
     * @return bool
     */
    public function isInstalled()
    {
        $response = $this->getConnection()->exec($this->service . ' -v');

        return strpos($response, 'No command') === false;
    }

    /**
     * @return bool|mixed|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()->exec('apt-cache showpkg ' . $this->service);

        $start = strpos($response, 'Versions:') + strlen('Versions:') + 1;
        $end = strpos($response, " ", $start);
        $length = $end - $start;

        return substr($response, $start, $length);
    }

}