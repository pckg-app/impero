<?php namespace Impero\Services\Service\Crypto;

use Impero\Servers\Record\Server;
use Impero\Servers\Service\ConnectionManager;
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

    protected $keys = [];

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
         * Decrypt encrypted file.
         */
        $compressedFile = $this->decrypt();

        /**
         * Delete original file.
         */
        $this->from->deleteFile($this->file);

        /**
         * Decompress file with Zip service.
         */
        $zipService = new Zip($this->to);
        $decompressedFile = $zipService->decompressFile($compressedFile);

        /**
         * Delete compressed file.
         */
        $this->from->deleteFile($compressedFile);

        return $decompressedFile;
    }

    /**
     * @return null|string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Exception
     */
    public function decrypt()
    {
        /**
         * File is then decrypted with gpg service.
         */
        $toConnection = $this->to
            ? $this->to->getConnection()
            : context()->getOrCreate(ConnectionManager::class)->createConnection();
        $toGpgService = (new GPG($toConnection));
        $decryptedFile = $toGpgService->decrypt($this->file);

        /**
         * Delete original file.
         */
        $this->from->deleteFile($this->file);

        return $decryptedFile;
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
        $zipService = new Zip($this->from);
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
     * @return null|string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function encrypt()
    {
        /**
         * File is then encrypted with gpg service.
         */
        $fromGpgService = new GPG($this->from);
        $toConnection = $this->to
            ? $this->to->getConnection()
            : context()->getOrCreate(ConnectionManager::class)->createConnection();
        $toGpgService = (new GPG($toConnection));
        $this->generateKeys($toGpgService);
        $encryptedFile = $fromGpgService->encrypt($this);

        /**
         * Delete original file.
         */
        $this->from->deleteFile($this->file);

        return $encryptedFile;
    }

    /**
     * @param GPG $GPG
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function generateKeys(GPG $GPG)
    {
        $this->keys = $GPG->generateThreesome();
    }

    public function getKeys()
    {
        return $this->keys;
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

    /**
     * @return Server|null
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @return Server|null
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

}