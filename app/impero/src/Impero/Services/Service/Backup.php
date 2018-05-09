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
    public function compressAndEncrypt(Server $server, $file, $keyFile, $service)
    {
        $compressedFile = $this->prepareDirectory($service . '/compressed') . $this->prepareFile();
        $server->compressFile($file, $compressedFile);
        $server->deleteFile($file);

        /**
         * Encrypt backup.
         */
        $encryptedFile = $this->prepareDirectory($service . '/encrypted') . $this->prepareFile();
        $server->encryptFile($compressedFile, $encryptedFile, $keyFile);
        $server->deleteFile($compressedFile);

        return $encryptedFile;
    }

    /**
     * @param Server $server
     * @param        $file
     * @param        $keyFile
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function decryptAndDecompress(Server $server, $file, $keyFile, $service)
    {
        /**
         * Decrypt backup.
         */
        $compressedCopy = $this->prepareDirectory($service . '/compressed') . $this->prepareFile();
        $server->decryptFile($file, $compressedCopy, $keyFile);
        $server->deleteFile($file);

        /**
         * Decompress file.
         */
        $backupCopy = $this->prepareDirectory($service . '/backups') . $this->prepareFile();
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
        $encryptedCopy = $this->prepareDirectory($service . '/temp') . $this->prepareFile();
        $from->transferFile($file, $encryptedCopy, $to);
        $from->deleteFile($file);

        return $encryptedCopy;
    }

    /**
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    protected function prepareFile()
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
         * This will create random 1024-4096 long key used for encryption saved as random file.
         */
        $opensslService = new OpenSSL($this->connection);
        $keyFile = $opensslService->createRandomHashFile();

        /**
         * Compress, encrypt, and delete all unused copies.
         */
        $encryptedFile = $this->compressAndEncrypt($from, $file, $keyFile, $service);

        /**
         * Transfer backup.
         */
        $encryptedCopy = $this->transfer($from, $to, $encryptedFile, $service);

        /**
         * Decrypt, decompress, and delete all unused copies.
         */
        $backupCopy = $this->decryptAndDecompress($to, $file, $keyFile, $service);

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

}