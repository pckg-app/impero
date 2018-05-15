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

    public function generateThreesome()
    {
        return $this->createRandomHashFiles();
    }

    public function getKeysDir()
    {
        return '/home/impero/.impero/service/backup/mysql/keys/';
    }

    /**
     * @param $destination
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function createRandomHashFiles()
    {
        $destination = $this->getKeysDir();
        if (is_dir($destination)) {
            mkdir($destination, 0400, true);
        }
        /**
         * Generate paths.
         */
        $private = sha1(Key::createNewRandomKey()->saveToAsciiSafeString());
        $public = sha1(Key::createNewRandomKey()->saveToAsciiSafeString());
        $key = sha1(Key::createNewRandomKey()->saveToAsciiSafeString());

        /**
         * Generate files.
         */
        $this->generatePrivateKey($destination . $private);
        $this->generatePublicKey($destination . $public, $destination . $private);
        $this->generatePassFile($destination . $key);

        return ['public' => $public, 'private' => $private, 'key' => $key];
    }

    public function generatePassFile($out)
    {
        /**
         * Generate encryption key.
         */
        $command = 'openssl rand -base64 128 > ' . $out . '.key.pem';
        $this->getConnection()->exec($command);
    }

    public function generatePrivateKey($out)
    {
        /**
         * Generate private key.
         */
        $command = 'openssl genrsa -out ' . $out . '.private.pem 4096'
            . ' -subj "/C=SI/ST=Pckg/L=Impero/O=Dis/CN=impero.foobar.si"';
        $this->getConnection()->exec($command);
    }

    public function generatePublicKey($out, $in)
    {
        /**
         * Generate public key.
         */
        $command = 'openssl rsa -in ' . $in . ' -outform PEM -pubout -out ' . $out;
        $this->getConnection()->exec($command);
    }

}