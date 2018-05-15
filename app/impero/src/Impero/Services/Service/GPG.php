<?php namespace Impero\Services\Service;

use Defuse\Crypto\Key;
use Impero\Servers\Record\Server;

class GPG extends AbstractService implements ServiceInterface
{

    protected $service = 'gpg';

    protected $name = 'GPG';

    public function getVersion()
    {
        return 'version todo';
    }

    public function getKeysDir()
    {
        return '/home/impero/.impero/service/backup/mysql/keys/';
    }

    /**
     * @return null
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function generateKey()
    {
        /**
         * Set key config.
         */
        $keyLength = 4096;
        $keyBatch = '%echo Generating a basic OpenPGP key
     Key-Type: RSA
     Key-Length: ' . $keyLength . '
     Subkey-Type: ELG-E
     Subkey-Length: ' . $keyLength . '
     Name-Real: Impero
     Name-Comment: Impero
     Name-Email: impero@impero
     Expire-Date: 1y
     %no-ask-passphrase
     %no-protection
     #Passphrase: pass
     %pubring foo.pub
     %secring foo.sec
     # Do a commit here, so that we can later print "done" :-)
     %commit
     %echo done';

        /**
         * Create config file.
         */
        $filename = $this->getKeysDir() . sha1random();
        $this->getConnection()->saveContent($filename, $keyBatch);

        /**
         * Generate key.
         */
        $command = 'gpg2 --batch --gen-key ' . $filename;
        $this->exec($command);
    }

    public function generateRevokeCertificate($key, $output)
    {
        /**
         * Generate revoke certificate.
         */
        $command = 'gpg2 --gen-revoke ' . $key . ' > ' . $output;
        $this->exec($command);
    }

    public function encrypt($input, $output)
    {
        $recipient = 'impero@impero';
        $command = 'gpg2 --output ' . $output . ' --trust-model always --encrypt --recipient ' . $recipient . ' ' . $input;
        $this->exec($command);
    }

    public function decrypt($input, $output)
    {
        $command = 'gpg2 --output ' . $output . ' --decrypt ' . $input;
        $this->exec($command);
    }

    public function deleteKeys($hash)
    {
        $this->deletePrivateKey($hash);
        $this->deletePublicKey($hash);
    }

    public function deletePrivateKey($hash)
    {
        $command = 'gpg2 --batch --yes --delete-secret-keys ' . $hash;
        $this->exec($command);
    }

    public function deletePublicKey($hash)
    {
        $command = 'gpg2 --batch --yes --delete-keys ' . $hash;
        $this->exec($command);
    }

    public function exportKeys($name)
    {
        $this->exportPublicKey($name);
        $this->exportPrivateKey($name);
    }

    public function exportPublicKey($name, $output)
    {
        $command = 'gpg --export ' . $name . ' > ' . $output;
        $this->exec($command);
    }

    public function exportPrivateKey($name, $output)
    {
        $command = 'gpg --export-secret-keys ' . $name . ' > ' . $output;
        $this->exec($command);
    }

    public function importPublicKey($file)
    {
        $command = 'gpg --import ' . $file;
        $this->exec($command);
    }

    public function importPrivateKey($file)
    {
        $command = 'gpg --import ' . $file;
        $this->exec($command);
    }

    public function listKeys()
    {
        $command = 'gpg --list-keys --fingerprint';
        $this->exec($command);
    }

    /**
     * Generate private key, public key, revoke certificate.
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function generateThreesome()
    {
        /**
         * Check for keys dir. Create if nonexistent.
         */
        $destination = $this->getKeysDir();
        if (!$this->getConnection()->dirExists($destination)) {
            $this->getConnection()->createDir($destination, 0400, true);
        }

        /**
         * Generate paths.
         */
        $private = sha1(Key::createNewRandomKey()->saveToAsciiSafeString());
        $public = sha1(Key::createNewRandomKey()->saveToAsciiSafeString());
        $cert = sha1(Key::createNewRandomKey()->saveToAsciiSafeString());

        $key = $this->generateKey();
        $certificate = $this->generateRevokeCertificate();
    }

    public function copyFileTo($file, Server $to)
    {
        /**
         * Copy public key from impero to $to.
         * Copy public key from $from to $to.
         */
        $this->getConnection()->stfpSend($file, $file);
    }

}