<?php namespace Impero\Mysql\Record;

use Exception;
use Impero\Mysql\Entity\Databases;
use Impero\Secret\Record\Secret;
use Impero\Servers\Record\Server;
use Impero\Services\Service\Backup;
use Impero\Services\Service\Mysql;
use Impero\Services\Service\OpenSSL;
use Pckg\Database\Record;

class Database extends Record
{

    protected $entity = Databases::class;

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

    /**
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function backup()
    {
        /**
         * Get current backup configuration.
         *
         * @T00D00 - backups will be managed by impero
         *         - impero will trigger backups at least twice per day, once per week, and once per month
         */
        $backupFile = '/backup/dbarray.conf';
        $connection = $this->server->getConnection();
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

        if (true) {
            return;
        }

        /**
         * This key will be associated with encrypted backup.
         */
        $keyFile = $this->server->getService(OpenSSL::class)->createRandomHashFile();

        /**
         * @T00D00 - key file needs to be associated with backup
         *         - how will we automate that?
         */
        $backupService = new Backup($this->server->getConnection());
        $backupFile = $backupService->createMysqlBackup($this);

        /**
         * Compress backup.
         */
        $encryptedBackup = $backupService->compressAndEncrypt($this->server, $backupFile, $keyFile, 'mysql');

        /**
         * Transfer encrypted backup to safe / cold location.
         */
        $backupService->encrypt($keyFile);
        $coldKey = $backupService->toCold($keyFile);
        $coldFile = $backupService->toCold($encryptedBackup);

        /**
         * Associate key with cold path so we can decrypt it later.
         * If someone gets coldpath encrypted files he cannot decrypt them without keys.
         * If someone gets encryption keys he won't have access to cold storage.
         * If someone gets encrypted files and keys he need mapper between them.
         * If someone gets mapper between coldpath and keys he would need keys and storage.
         */
        Secret::create([
            'key'  => $coldKey,
            'file' => $coldFile,
        ]);
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
        $mysqlService = new Mysql($this->server->getConnection());
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
        $backupMasterService = new Backup($this->server->getConnection());

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
         * Let backaup service take care of full transfer.
         */
        $backupMasterService->processFullTransfer($this->server, $slaveServer, $backupFile, 'mysql');

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
        $command = 'mysql -u impero ' . $this->name . ' < ' . $file;
        $server->exec($command);

        $server->deleteFile($file);
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
        (new Mysql($this->server->getConnection()))->requireMysqlMasterReplication();
    }

}
