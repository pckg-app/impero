<?php namespace Impero\Mysql\Record;

use Exception;
use Impero\Mysql\Entity\Databases;
use Impero\Secret\Record\Secret;
use Impero\Servers\Record\Server;
use Impero\Servers\Service\ConnectionManager;
use Impero\Services\Service\Backup;
use Impero\Services\Service\Connection\Connectable;
use Impero\Services\Service\Connection\ConnectionInterface;
use Impero\Services\Service\Crypto\Crypto;
use Impero\Services\Service\Mysql;
use Pckg\Database\Record;

class Database extends Record implements Connectable
{

    protected $entity = Databases::class;

    public function getConnection() : ConnectionInterface
    {
        return $this->server->getConnection();
    }

    /**
     * Build edit url.
     *
     * @return string
     */
    public function getEditUrl()
    {
        return url('database.edit', ['database' => $this]);
    }

    /**
     * Build delete url.
     *
     * @return string
     */
    public function getDeleteUrl()
    {
        return url('database.delete', ['database' => $this]);
    }

    public function setUserIdByAuthIfNotSet()
    {
        if (!$this->user_id) {
            $this->user_id = auth()->user('id');
        }

        return $this;
    }

    public function requireScriptBackup()
    {
        /**
         * Get current backup configuration.
         *
         * @T00D00 - backups will be managed by impero
         *         - impero will trigger backups at least twice per day, once per week, and once per month
         */
        $backupFile = '/backup/dbarray.conf';
        $connection = $this->getConnection();
        $currentBackup = $connection->sftpRead($backupFile);
        $databases = explode("\n", $currentBackup);

        /**
         * Check for existance.
         */
        if (!in_array($this->name, $databases)) {
            /**
             * Add to file if nonexistent.
             */
            $connection->exec('sudo echo "' . $this->name . '" >> ' . $backupFile);
        }
    }

    /**
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function backup()
    {
        /**
         * Establish connection to server and create mysql dump.
         */
        $backupService = new Backup($this->getConnection());
        $localBackupService = new Backup(context()->getOrCreate(ConnectionManager::class)->createConnection());
        $backupFile = $backupService->createMysqlBackup($this);

        /**
         * Compress and encrypt backup.
         */
        $crypto = new Crypto($this->server, null, $backupFile);
        $crypto->compressAndEncrypt();

        /**
         * Transfer encrypted backup, private key and certificate to safe / cold location.
         */
        try {
            d('file to cold');
            $coldFile = $backupService->toCold($crypto->getFile());
            $keys = $crypto->getKeys();
            d('private to cold');
            $coldPrivate = $localBackupService->toCold($keys['private']);
            d('cert to cold');
            $coldCert = $localBackupService->toCold($keys['cert']);
        } catch (\Throwable $e) {
            dd(exception($e));
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

        Secret::create(
            [
                'file' => $coldFile,
                'keys' => json_encode(
                    [
                        'private' => $coldPrivate,
                        'cert'    => $coldCert,
                    ]
                ),
            ]
        );

        return true;
    }

    /**
     * @param Server                         $to
     * @param null|\Pckg\Database\Repository $coldFile
     *
     * @return mixed|void
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function restoreTo(Server $to, $coldFile)
    {
        /**
         * Get matching secret data.
         */
        $secret = Secret::getOrFail(['file' => $coldFile]);

        /**
         * Transfer from cold to restore server.
         */
        $toBackupService = new Backup($to);
        $encryptedFile = $toBackupService->fromCold($coldFile);

        /**
         * Decrypt and decompress file.
         */
        $crypto = new Crypto($this->server, null, $encryptedFile);
        $crypto->setKeys($secret->arrayKeys);
        $file = $crypto->decryptAndDecompress();

        /**
         * Now, let's import that file.
         */
        $toBackupService->importMysqlBackup($this, $file);

        /**
         * Find out import binlog location.
         */
        $toMysqlService = new Mysql($to);
        $binlogLocation = $toMysqlService->getBinlogLocation($file);

        /**
         * And delete backup file.
         */
        $to->deleteFile($file);

        /**
         * Sync database from import binlog location to the end.
         */
        $toMysqlService->syncDatabaseToBinlogLocation($this, $binlogLocation);
    }

    public function importFile($file)
    {
        $this->server->getMysqlConnection()->pipeIn($file, $this->name);
    }

    public function query($sql, $bind)
    {
        $mysqlConnection = $this->server->getMysqlConnection();

        return $mysqlConnection->query($this->name, $sql, $bind);
    }

    public function getReplicationConfigLocation()
    {
        return '/etc/mysql/conf.d/replication.cnf';
    }

    /**
     * Enable mysql binlog and replication on master server.
     *
     * @throws Exception
     */
    public function replicateOnMaster()
    {
        /**
         * Check on $server that replication is active.
         * Check that entry is found in /etc/mysql/conf.d/replication.cnf
         */
        $mysqlService = new Mysql($this->getConnection());
        if ($mysqlService->isReplicatedOnMaster($this)) {
            return;
        }

        $mysqlService->replicateOnMaster($this);
    }

    /**
     * @param Server $slaveServer
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function replicateTo(Server $slaveServer)
    {
        /**
         * Generate new passphrase for backup service: private/mysql/backup/keys/$hash.
         * This key will be transfered from impero to master and from impero to slave.
         * It will be deleted immediately after we don't need it anymore.
         */
        $mysqlSlaveService = (new Mysql($slaveServer->getConnection()));
        $backupMasterService = new Backup($this->getConnection());

        /**
         * Check if database is alredy replicated.
         */
        if ($mysqlSlaveService->isReplicatedOnSlave($this)) {
            throw new Exception('Database is already replicated on slave.');
        }

        /**
         * Put slave out of cluster and wait few seconds for all connections to be closed and configuration to take in effect.
         *
         * @T00D00 - configure HAProxy for mysql loadbalancing
         */

        /**
         * Stop slave down so it won't sync with master.
         *
         * @T00D00 - take down only $database, then sync only $database from binlog.
         */
        $mysqlSlaveService->stopSlave();

        /**
         * Create backup.
         */
        $backupFile = $backupMasterService->createMysqlBackup($this);

        /**
         * Resume slave until backup.
         */
        $this->syncSlaveUntilBackup($backupFile, $slaveServer);

        /**
         * Let backup service take care of full transfer.
         */
        $crypto = new Crypto($this->server, $slaveServer, $backupFile);
        $crypto->processFullTransfer();

        /**
         * Create database?
         * Import backup.
         */
        $this->importBackup($backupFile, $slaveServer);

        /**
         * Start slave.
         */
        $mysqlSlaveService->startSlave();

        /**
         * Wait few seconds for slave to get in sync.
         * Then refresh cluster configuration.
         */
        //
    }

    /**
     * @param $backupPath
     *
     * @throws Exception
     */
    public function syncSlaveUntilBackup($backupPath, Server $slaveServer)
    {
        /**
         * Read binlog position and resume slave sync.
         * -- CHANGE MASTER TO MASTER_LOG_FILE='mysql-bin.002142', MASTER_LOG_POS=752877;
         */
        $positionLine = $this->server->exec('head ' . $backupPath)[0] ?? null;
        if (!$positionLine) {
            throw new Exception('Cannot parse binlog position from backup.');
        }

        $command = trim(str_replace('-- CHANGE MASTER TO', 'START SLAVE UNTIL', $positionLine));

        if (strpos($command, 'START SLAVE UNTIL') !== 0) {
            throw new Exception('Error preparing resume slave statement');
        }

        $slaveServer->execSql($command);
    }

    /**
     * @param        $file
     * @param Server $server
     *
     * @throws Exception
     */
    public function importBackup($file, Server $server)
    {
        $backupService = new Backup($server);
        $backupService->importMysqlBackup($this, $file);
    }

    /**
     * @param $data
     *
     * @return Database|Record
     * @throws Exception
     */
    public static function createFromPost($data)
    {
        /**
         * Save database in our database.
         */
        $database = Database::create(['name' => $data['name'], 'server_id' => $data['server_id']]);

        /**
         * Connect to proper mysql server and execute sql.
         */
        $server = Server::gets(['id' => $data['server_id']]);

        /**
         * Receive mysql connection?
         */
        $mysqlConnection = $server->getMysqlConnection();

        $sql = 'CREATE DATABASE IF NOT EXISTS `' . $data['name'] . '` CHARACTER SET `utf8` COLLATE `utf8_general_ci`';
        $mysqlConnection->execute($sql);

        return $database;
    }

    /**
     * @throws Exception
     */
    public function requireMysqlMasterReplication()
    {
        (new Mysql($this->getConnection()))->requireMysqlMasterReplication();
    }

}
