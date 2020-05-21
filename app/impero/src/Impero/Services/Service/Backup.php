<?php namespace Impero\Services\Service;

use Exception;
use Impero\Mysql\Entity\Databases;
use Impero\Mysql\Record\Database;
use Impero\Secret\Record\Secret;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Impero\Servers\Service\ConnectionManager;
use Impero\Services\Service\Crypto\Crypto;
use Impero\Storage\Record\Storage;

/**
 * Class Backup
 *
 * @package Impero\Services\Service
 */
class Backup extends AbstractService implements ServiceInterface
{

    const TYPE_FILE = 'file';

    const TYPE_DIR = 'dir';

    /**
     * @var string
     */
    protected $service = 'backup';

    /**
     * @var string
     */
    protected $name = 'Backup';

    /**
     * @return string
     */
    public function getVersion()
    {
        return 'version todo';
    }

    /**
     * @param Database $database
     *
     * @return string
     */
    public function createMysqlBackup(Database $database)
    {
        /**
         * Create task so we can track it's progress.
         */
        $task = Task::create('Dumping database #' . $database->id);

        return $task->make(function () use ($database) {
            /**
             * This commands will always executed by impero user, which is always available on filesystem.
             *
             * @T00D00 - read password from .cnf in impero home dir?
             *         - make sure that backup path exists and is writable
             */
            $user = 'impero';
            $backupFile = $this->prepareDirectory('random') . sha1random();
            $flags = '--routines --triggers --skip-opt --order-by-primary --create-options --compact --master-data=2 --single-transaction --extended-insert --add-locks --disable-keys';

            $dumpCommand = 'mysqldump ' . $flags . ' -u ' . $user . ' ' . $database->name . ' > ' . $backupFile;
            $this->getConnection()->exec($dumpCommand);

            return $backupFile;
        });
    }

    /**
     * @param Server $server
     * @param Storage $storage
     *
     * @return mixed
     * @throws \Exception
     */
    public function createStorageBackup(Server $server, Storage $storage)
    {
        $zip = (new Zip($server->getConnection()));

        return $zip->compressDirectory($storage->location);
    }

    public function createDirectoryBackup($dir)
    {
        $zip = (new Zip($this->getConnection()));

        return $zip->compressDirectory($dir);
    }

    public function createSslBackup($sslDirectory, $sslDomain)
    {
        /**
         * We want to backup old and current certificates.
         * cert.pem fullchain.pem privkey.pem + chain.pem
         */
        $letsencryptDir = '/etc/letsencrypt/';

        $zip = (new Zip($this->getConnection()));

        return $zip->compressDirectories([
            $letsencryptDir . 'archive/' . $sslDomain . '/', // all certificates
            $letsencryptDir . 'renewal/' . $sslDomain . '.conf', // renewal config
            $letsencryptDir . 'live/' . $sslDomain . '/', // only symlinks
        ]);
    }

    public function importSslBackup($dir, $sslDomain)
    {
        $letsencryptDir = '/etc/letsencrypt/';
        $commands = [
            'sudo mkdir -p ' . $letsencryptDir . 'archive/' . $sslDomain,
            'sudo mkdir -p ' . $letsencryptDir . 'live/' . $sslDomain,
            'sudo mkdir -p ' . $letsencryptDir . 'renewal',
            'sudo mv ' . $dir . $letsencryptDir . 'archive/' . $sslDomain . '/* ' . $letsencryptDir . '/archive/' . $sslDomain . '/',
            'sudo mv ' . $dir . $letsencryptDir . 'renewal/* ' . $letsencryptDir . '/renewal/',
        ];

        /**
         * Link needs to be made by hand since it is not in the .zip.
         */
        $cd = 'cd ' . $letsencryptDir . 'live/' . $sslDomain;

        foreach ($commands as $command) {
            $this->getConnection()->exec($command);
        }

        /**
         * We need to find latest number.
         */
        $num = 0;
        do {
            $num++;
        } while ($this->getConnection()->fileExists($letsencryptDir . 'archive/' . $sslDomain . '/cert' . $num . '.pem'));
        $num--;

        $subcommands = [
            'sudo ln -s ../../archive/' . $sslDomain . '/cert' . $num . '.pem cert.pem',
            'sudo ln -s ../../archive/' . $sslDomain . '/chain' . $num . '.pem chain.pem',
            'sudo ln -s ../../archive/' . $sslDomain . '/fullchain' . $num . '.pem fullchain.pem',
            'sudo ln -s ../../archive/' . $sslDomain . '/privkey' . $num . '.pem privkey.pem',
        ];

        foreach ($subcommands as $command) {
            $this->getConnection()->exec($cd . ' && ' . $command);
        }

        // delete file?
    }

    /**
     * @param Database $database
     * @param          $file
     */
    public function importMysqlBackup(Database $database, $file)
    {
        $task = Task::create('Importing backup to MySQL');

        return $task->make(function () use ($database, $file) {
            $output = $this->exec('mysql -u impero -e \'SHOW DATABASES WHERE `Database` = "' . $database->name . '"\'');
            if (trim($output)) {
                /**
                 * Already existing.
                 */
                return;
            }
            $o = null;
            $e = null;
            $r = $this->exec('mysql -u impero -e \'CREATE DATABASE `' . $database->name . '`\'', $o, $e); // IF NOT EXISTS

            $command = 'mysql -u impero ' . $database->name . ' -e \'SET FOREIGN_KEY_CHECKS=0; SOURCE ' . $file .
                '; SET FOREIGN_KEY_CHECKS=1;\'';

            $o = null;
            $e = null;
            $r = $this->getConnection()->exec($command, $o, $e);

            return $r;
        });
    }

    public function importStorageBackup($from, $to)
    {
        /**
         * $from dir contains whole directory structure with multiple directories where each needs to be moved.
         * We will move $from/each to $to/each directories.
         */
        $this->getConnection()->exec('mkdir -p ' . $to);
        $this->getConnection()->exec('mv ' . $from . '/* ' . $to . '/');
        // delete files and dirs?
    }


    /**
     * @param $file
     *
     * @return string
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\FileExistsException
     */
    public function toCold($file)
    {
        $task = Task::create('Uploading file to cold location');

        return $task->make(function () use ($file) {
            $do = (new DigitalOcean($this->getConnection()));

            return $do->uploadToSpaces($file);
        });
    }

    /**
     * @param $file
     *
     * @return string
     */
    public function fromCold($file)
    {
        $do = (new DigitalOcean($this->getConnection()));

        return $do->downloadFromSpaces($file);
    }

    public static function fullColdRestore(Backup $backupService, Secret $secret, callable $importer, Server $server, $type = Backup::TYPE_FILE)
    {
        /**
         * Fetch file from Spaces.
         */
        $file = $secret->file;
        d('download ' . $file);
        $zip = $backupService->fromCold($file);
        d("ZIP: " . $zip);

        /**
         * Unzip the file.
         */
        $zipMethod = new Zip($server);
        $unzipped = $type === static::TYPE_FILE
            ? $zipMethod->decompressFile($zip)
            : $zipMethod->decompressDirectory($zip);
        d('Unzipped: ' . $unzipped);

        $ok = $importer($unzipped);

        if (!$ok) {
            return;
        }

        // delete file from local env? keep it on spaces.
    }

    public static function fullColdBackup(Backup $backupService, callable $file, Server $fromServer, array $secret)
    {
        /**
         * Establish connection to server.
         */
        $localBackupService = new Backup(context()->getOrCreate(ConnectionManager::class)->createConnection());
        $createdAt = date('Y-m-d H:i:s');

        /**
         * Create MySQL dump or storage dump .zip.
         */
        $backupFile = $file($backupService);

        if (!$backupFile) {
            throw new Exception('Backed up file not set?');
        }

        /**
         * Compress and encrypt backup on remote server.
         */
        $crypto = new Crypto($fromServer, null, $backupFile);
        $encryptedFile = $crypto->compressAndEncrypt();

        if (!$encryptedFile) {
            throw new Exception('Encrypted file not set?');
        }

        /**
         * Transfer encrypted backup, private key and certificate to safe / cold location.
         */
        $coldFile = $backupService->toCold($crypto->getFile());
        if (!$coldFile) {
            throw new Exception('Cold file not set?');
        }

        $keys = $crypto->getKeys();
        /**
         * @T00D00
         */
        $coldPrivate = $keys['private'] ?? null;
        $coldCert = $keys['cert'] ?? null;
        //$coldPrivate = $localBackupService->toCold($keys['private']);
        //$coldCert = $localBackupService->toCold($keys['cert']);

        $task = Task::create('Associating cold file with keys');
        $task->make(function () use ($coldFile, $coldPrivate, $coldCert, $createdAt, $secret) {
            /**
             * @T00D00 - decrypt keys?
             * Associate key with cold path so we can decrypt it later.
             * If someone gets coldpath encrypted files he cannot decrypt them without keys.
             * If someone gets encryption keys he won't have access to cold storage.
             * If someone gets encrypted files and keys he need mapper between them.
             * If someone gets mapper between coldpath and keys he would need keys and storage.
             * .......
             * We also want to associate backup with database and server maybe? So we can actually know right context. :)
             * So, when we want to restore db backup or storage backup, we go to database or mount point and see list of
             * available backups. User selects backup to restore, and target server, system checks for secret links,
             * transfers, encrypts and imports file; or download encrypted file + private key package, both repackaged and
             * encrypted with per-download-set password.
             * .......
             * Maybe we should store secret keys in different database for better security?
             *         When decrypting we need to know which private key unlocks with file and which cert cancels private key.
             *         Additionally we'll encrypt private key with password file.
             */

            /**
             * Now tell system that this file is backup of specific database?
             */
            $secretData = [
                'file' => $coldFile,
                'keys' => json_encode(array_merge([
                    'created_at' => $createdAt,
                    'private' => $coldPrivate,
                    'cert' => $coldCert,
                ], $secret)),
            ];
            Secret::create($secretData);
        });

        return $coldFile;
    }

}