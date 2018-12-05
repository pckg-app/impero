<?php namespace Impero\Services\Service\Crypto;

use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Impero\Servers\Service\ConnectionManager;
use Impero\Services\Service\Connection\LocalConnection;
use Impero\Services\Service\GPG;
use Impero\Services\Service\Zip;

/**
 * Class Crypto
 *
 * @package Impero\Services\Service\Crypto
 */
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

    /**
     * @var
     */
    protected $compressed;

    /**
     * @var
     */
    protected $encrypted;

    /**
     * @var array
     */
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
     * @return array
     */
    public function getKeys()
    {
        return $this->keys;
    }

    public function setKeys($keys)
    {
        $this->keys = $keys;

        return $this;
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

    /**
     * @param GPG $GPG
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function generateKeys(GPG $GPG)
    {
        $this->keys = $GPG->generateThreesome();
    }

    /**
     * @param Server $server
     * @param        $file
     *
     * @throws \Exception
     */
    public function replaceFile(Server $server, $file)
    {
        $server->getConnection()->deleteFile($this->file);
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
        $task = Task::create('Transferring file');

        return $task->make(
            function() {
                /**
                 * Compress, encrypt, and delete all unused copies.
                 * We know that we'll transfer file from $from to $to server.
                 */
                $this->compressAndEncrypt();

                /**
                 * Transfer backup.
                 */
                $this->transfer();

                /**
                 * Decrypt, decompress, and delete all unused copies.
                 */
                return $this->decryptAndDecompress();
            }
        );
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
        $this->replaceFile($this->from, $compressedFile);

        return $compressedFile;

        /**
         * Encrypt compressed file.
         */
        return $encryptedFile = $this->encrypt();
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
        $task = Task::create('Encrypting file');

        return $task->make(
            function() {
                /**
                 * File is then encrypted with gpg service.
                 * Delete original after usage.
                 */
                $fromGpgService = new GPG($this->from);
                $encryptedFile = $fromGpgService->encrypt($this);
                $this->replaceFile($this->from, $encryptedFile);

                return $encryptedFile;
            }
        );
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
         * Delete original after usage.
         */
        $compressedFile = $this->decrypt();

        /**
         * Decompress file with Zip service.
         * Delete original after usage.
         */
        $zipService = new Zip($this->to);
        $decompressedFile = $zipService->decompressFile($compressedFile);
        $this->replaceFile($this->to, $decompressedFile);

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
        $decryptedFile = $toGpgService->decrypt($this);
        $this->replaceFile($this->to, $decryptedFile);

        return $decryptedFile;
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
        $encryptedCopy = $this->prepareDirectory('random') . sha1random();
        $this->from->transferFile($this->file, $encryptedCopy, $this->to);
        $this->replaceFile($this->from, $encryptedCopy);

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

        $root = $this->to->getConnection() instanceof LocalConnection
            ? path('private')
            : '/home/impero/impero/';
        $dir = $root . 'service/random';// . $dir;

        if ($this->to->getConnection()->dirExists($dir)) {
            return $dir . '/';
        }

        $this->to->getConnection()->exec('mkdir -p ' . $dir);

        return $dir . '/';
    }

}