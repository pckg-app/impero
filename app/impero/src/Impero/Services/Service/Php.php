<?php namespace Impero\Services\Service;

use Impero\Dependencies\Dependency\Curl;
use Impero\Dependencies\Dependency\Unzip;
use Impero\Dependencies\Dependency\Wget;
use Impero\Dependencies\Dependency\Xvfb;
use Impero\Dependencies\Dependency\Zip;

class Php extends AbstractService implements ServiceInterface
{

    protected $service = 'php';

    protected $name = 'PHP';

    protected $install = 'php7.0 php7.0-curl php7.0-intl php-curl php7.0-xml php7.0-mbstring php7.0-zip';

    protected $dependencies = [
        Wget::class,
        Xvfb::class,
        Zip::class,
        Unzip::class,
        Curl::class,
    ];

    public function isInstalled()
    {
        $response = $this->getConnection()
                         ->exec($this->service . ' -v');

        return strpos($response, 'No command') === false;
    }

    public function getVersion()
    {
        $response = $this->getConnection()
                         ->exec($this->service . ' -v');

        $start = strpos($response, 'PHP ') + strlen('PHP ');
        $end = strpos($response, " ", $start);
        $length = $end - $start;

        return substr($response, $start, $length);
    }

    public function getStatus()
    {
        return 'ok';
    }

}