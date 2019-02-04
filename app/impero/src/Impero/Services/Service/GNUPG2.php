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
        //sudo apt-get install gnupg2 -y
        // sudo apt-get install gnupg2 -y
        // apt-get install haveged -y
        // /etc/default/haveged
        // DAEMON_ARGS="-w 1024"
        // sudo update-rc.d haveged defaults
        // rm -rf ~/.gnupg
        // mkdir ~/.gnupg
        // chmod 0700 ~/.gnupg
        // mkdir /var/www/.gnupg
        $response = $this->getConnection()->exec('ufw version');

        $start = strpos($response, 'ufw ') + strlen('ufw ');
        $end = strpos($response, "\n");
        $length = $end - $start;

        return substr($response, $start, $length);
    }

}