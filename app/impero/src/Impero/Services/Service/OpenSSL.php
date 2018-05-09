<?php namespace Impero\Services\Service;

use Defuse\Crypto\Key;

class OpenSSL extends AbstractService implements ServiceInterface
{

    protected $service = 'openssl';

    protected $name = 'OpenSSL';

    public function getVersion()
    {
        return 'version todo';
    }

    /**
     * @param $destination
     *
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function createRandomHashFile()
    {
        $destination = '/home/impero/.impero/service/backup/mysql/keys/';
        $hash = sha1(Key::createNewRandomKey()->saveToAsciiSafeString());

        $this->getConnection()->exec('openssl rand -base64 ' . rand(1024, 4096) . ' > ' . $destination . $hash);

        return $destination . $hash;
    }

}