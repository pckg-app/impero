<?php namespace Impero\Services\Service;

use Defuse\Crypto\Key;
use Impero\Mysql\Record\Database;
use Impero\Servers\Record\Server;

class Backup extends AbstractService implements ServiceInterface
{

    protected $service = 'backup';

    protected $name = 'Backup';

    public function getVersion()
    {
        return 'version todo';
    }

    /**
     * @param Server $server
     * @param        $file
     * @param        $keyFile
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function compressAndEncrypt(Server $from, Server $to = null, $file, $service)
    {
        $compressedFile = $this->prepareDirectory($service . '/compressed') . $this->prepareRandomFile();
        $from->compressFile($file, $compressedFile);
        $from->deleteFile($file);
        $encryptedFile = $this->encrypt($from, $to, $file, $service);

        return $encryptedFile;
    }

    /**
     * @param Server $server
     * @param        $file
     * @param        $keyFile
     * @param        $service
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function encrypt(Server $from, Server $to = null, $file, $service)
    {
        /**
         * Encrypt backup.
         *
         * @T00D00 - when encrypting we need to know for which purpose:
         *         - replication transfer is encrypted by remote public key and decrypted by remote private key
         *         - backups are encrypted with each-time-new key set
         *         - mapper is saved in impero database, public keys in storage, private keys in cold storage
         *         ....
         * On source server we generate random key and encrypt it with target public key.
         * We also encrypt transfered file with target public key.
         * Encrypted file and key are then transfered to target server.
         * They can be encrypted with private pair of generated keys on target server in this session.
         * ......
         * On source server we generate random key and encrypt it with on /impero generated public key.
         * We also encrypt transfered file with /impero generated public key.
         * Encrypted file is transfered to safe location.
         * Encrypted key is transfered to /impero.
         * When file is needed for decryption, we transfer it and encryption key to target server.
         * New pair of keys is generated on target server. Private key and new encryption key on /impero are encrypted
         * with new public key, transfered to target server, and decrypted with new private key. Then original file
         * is encrypted with decrypted encryption key and decrypted private key.
         */
        $encryptedFile = $this->prepareDirectory($service . '/encrypted') . $this->prepareRandomFile();
        $from->encryptFile($file, $encryptedFile, $to);
        $from->deleteFile($file);
    }

    /**
     * @param Server $server
     * @param        $file
     * @param        $keyFile
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function decryptAndDecompress(Server $server, $file, $keyFiles, $service)
    {
        /**
         * Decrypt backup.
         */
        $compressedCopy = $this->prepareDirectory($service . '/compressed') . $this->prepareRandomFile();
        $server->decryptFile($file, $compressedCopy, $keyFiles);
        $server->deleteFile($file);

        /**
         * Decompress file.
         */
        $backupCopy = $this->prepareDirectory($service . '/backups') . $this->prepareRandomFile();
        $server->decompressFile($compressedCopy, $backupCopy);
        $server->deleteFile($compressedCopy);

        return $backupCopy;
    }

    /**
     * @param Server $from
     * @param Server $to
     * @param        $file
     * @param        $service
     *
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function transfer(Server $from, Server $to, $file, $service)
    {
        $encryptedCopy = $this->prepareDirectory($service . '/temp') . $this->prepareRandomFile();
        $from->transferFile($file, $encryptedCopy, $to);
        $from->deleteFile($file);

        return $encryptedCopy;
    }

    /**
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    protected function prepareRandomFile()
    {
        return sha1(Key::createNewRandomKey()->saveToAsciiSafeString());
    }

    protected function prepareDirectory($dir)
    {
        $dir = '/home/impero/.impero/service/backup/' . $dir;

        if ($this->getConnection()->dirExists($dir)) {
            return $dir;
        }

        $this->getConnection()->exec('mkdir -p ' . $dir);

        return $dir;
    }

    /**
     * @param Server $from
     * @param Server $to
     * @param        $file
     * @param        $service
     *
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function processFullTransfer(Server $from, Server $to, $file, $service)
    {
        /**
         * Compress, encrypt, and delete all unused copies.
         * We know that we'll transfer file from $from to $to server.
         */
        $encryptedFile = $this->compressAndEncrypt($from, $to, $file, $service);

        /**
         * Transfer backup.
         */
        $encryptedCopy = $this->transfer($from, $to, $encryptedFile, $service);

        /**
         * Decrypt, decompress, and delete all unused copies.
         */
        $backupCopy = $this->decryptAndDecompress($to, $encryptedCopy, $keyFiles, $service);

        return $backupCopy;
    }

    public function createMysqlBackup(Database $database)
    {
        /**
         * This commands will always executed by impero user, which is always available on filesystem.
         *
         * @T00D00 - read password from .cnf in impero home dir?
         *         - make sure that backup path exists and is writable
         */
        $user = 'impero';
        $backupPath = $this->prepareDirectory('mysql/backup');
        $file = $this->name . '_' . date('Ymdhis') . '_' . $database->server_id . '.sql';
        $flags = '--routines --triggers --skip-opt --order-by-primary --create-options --compact --master-data=2 --single-transaction --extended-insert --add-locks --disable-keys';

        $dumpCommand = 'mysqldump ' . $flags . ' -u ' . $user . ' ' . $this->name . ' > ' . $backupPath . $file;
        $this->getConnection()->exec($dumpCommand);

        return $file;
    }

    public function toCold($file)
    {
        /**
         * @T00D00 - Transfer image to digital ocean spaces?
         */
        $this->getConnection()->exec('rm ' . $file);
    }

}