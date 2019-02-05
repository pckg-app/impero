<?php namespace Impero\Storage\Record;

use Impero\Secret\Record\Secret;
use Impero\Servers\Record\Server;
use Impero\Servers\Service\ConnectionManager;
use Impero\Services\Service\Backup;
use Impero\Services\Service\Crypto\Crypto;
use Impero\Services\Service\Lsyncd;
use Impero\Storage\Entity\Storages;
use Pckg\Database\Record;

class Storage extends Record
{

    protected $entity = Storages::class;

    public function backupLive(Server $server)
    {
        $lsyncd = new Lsyncd($server->getConnection());
        $lsyncd->sync($server, $this);
    }

    /**
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function backup(Server $server)
    {
        return;
        /**
         * Establish connection to server and create mysql dump.
         */
        $backupService = new Backup($this->getConnection());
        $localBackupService = new Backup(context()->getOrCreate(ConnectionManager::class)->createConnection());
        $backupFile = $backupService->createStorageBackup($server, $this);

        /**
         * Encrypt backup.
         */
        $crypto = new Crypto($this->server, null, $backupFile);
        $crypto->encrypt();

        /**
         * Transfer encrypted backup, private key and certificate to safe / cold location.
         */
        try {
            $coldFile = $backupService->toCold($crypto->getFile());
            $keys = $crypto->getKeys();
            $coldPrivate = $localBackupService->toCold($keys['private']);
            $coldCert = $localBackupService->toCold($keys['cert']);
        } catch (\Throwable $e) {
            ddd(exception($e));
        }

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

        Secret::create([
                           'file' => $coldFile,
                           'keys' => json_encode([
                                                     'private' => $coldPrivate,
                                                     'cert'    => $coldCert,
                                                 ]),
                       ]);
    }

}