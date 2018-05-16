<?php namespace Impero\Services\Service\Crypto;

use Impero\Servers\Record\Server;
use Impero\Services\Service\GPG;
use Impero\Services\Service\Zip;

class Crypto
{

    /**
     * @var Server
     */
    protected $from;

    /**
     * @var Server
     */
    protected $to;

    /**
     * @var string
     */
    protected $file;

    protected $compressed;

    protected $encrypted;

    /**
     * Crypto constructor.
     *
     * @param string      $file
     * @param Server|null $from
     * @param Server|null $to
     */
    public function __construct(Server $from = null, Server $to = null, string $file)
    {
        $this->from = $from;
        $this->to = $to;
        $this->file = $file;
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
    public function processFullTransfer()
    {
        /**
         * Compress, encrypt, and delete all unused copies.
         * We know that we'll transfer file from $from to $to server.
         */
        $encryptedFile = $this->compressAndEncrypt();

        /**
         * Transfer backup.
         */
        $encryptedCopy = $this->transfer();

        /**
         * Decrypt, decompress, and delete all unused copies.
         */
        $backupCopy = $this->decryptAndDecompress();

        return $backupCopy;
    }

    /**
     * @param Server $server
     * @param        $file
     * @param        $keyFile
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function decryptAndDecompress()
    {
        /**
         * Create encrypt service.
         */
        $gpgService = new GPG($this->to);
        $decryptedFile = $gpgService->decrypt();

        /**
         * Decrypt backup.
         */
        $compressedCopy = $this->prepareDirectory('crypto/compressed') . sha1random();
        $this->to->decryptFile($this->file, $compressedCopy, $keyFiles);
        $this->to->deleteFile($this->file);

        /**
         * Decompress file.
         */
        $backupCopy = $this->prepareDirectory('crypto/backups') . sha1random();
        $this->to->decompressFile($compressedCopy, $backupCopy);
        $this->to->deleteFile($compressedCopy);

        return $backupCopy;
    }

    /**
     * @param Server $server
     * @param        $file
     * @param        $keyFile
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function compressAndEncrypt()
    {
        /**
         * Compress file with Zip service.
         */
        $zipService = new Zip($this->getConnection());
        $compressedFile = $zipService->compressFile($this->file);

        /**
         * Delete original file.
         */
        $this->from->deleteFile($this->file);

        /**
         * Encrypt compressed file.
         */
        $encryptedFile = $this->encrypt();

        /**
         * Delete compressed file.
         */
        $this->from->deleteFile($compressedFile);

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
    public function encrypt()
    {
        /**
         * File is then encrypted with gpg service.
         */
        $fromGpgService = new GPG($this->from);
        $encryptedFile = $fromGpgService->encrypt($this->from, $this->to, $this->file);

        /**
         * Delete original file.
         */
        $this->from->deleteFile($this->file);

        return $encryptedFile;
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
    public function transfer()
    {
        $encryptedCopy = $this->prepareDirectory('crypto/temp') . sha1random();
        $this->from->transferFile($this->file, $encryptedCopy, $this->to);
        $this->from->deleteFile($this->file);

        return $encryptedCopy;
    }

    /**
     * @param $dir
     *
     * @return string
     * @throws \Exception
     */
    protected function prepareDirectory($dir)
    {
        $dir = '/home/impero/.impero/service/' . $dir;

        if ($this->from->getConnection()->dirExists($dir)) {
            return $dir;
        }

        $this->from->getConnection()->exec('mkdir -p ' . $dir);

        return $dir;
    }

}