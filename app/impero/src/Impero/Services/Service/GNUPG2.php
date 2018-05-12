<?php namespace Impero\Services\Service;

class GNUPG2 extends AbstractService implements ServiceInterface
{

    protected $service = 'gnupg2';

    protected $name = 'gpg2';

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