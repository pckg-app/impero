<?php namespace Impero\Mysql\Record;

use Exception;
use Impero\Mysql\Entity\Databases;
use Impero\Secret\Record\Secret;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\ServersMorph;
use Impero\Servers\Record\Task;
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
     * @return ConnectionInterface|Connectable
     */
    public function getConnection()
    {
        return $this->server->getConnection();
    }

    /**
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function backup()
    {
        /**
         * Create task so we can track its progress.
         */
        $task = Task::create('Creating database #' . $this->id . ' cold backup');

        /**
         * Execute backup task.
         */
        $task->make(function () {

            $backupService = new Backup($this->getConnection());

            Backup::fullColdBackup($backupService, function ($backupService) {
                return $backupService->createMysqlBackup($this);
            }, $this->server, [
                'morph_id' => Databases::class,
                'poly_id' => $this->id,
                'type' => 'mysql:dump',
            ]);
        });

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
        $task = Task::create('Replicating db #' . $this->id . ' to ' . $slaveServer->ip);

        $task->make(function() use ($slaveServer) {
            /**
             * Generate new passphrase for backup service: private/mysql/backup/keys/$hash.
             * This key will be transfered from impero to master and from impero to slave.
             * It will be deleted immediately after we don't need it anymore.
             */
            $mysqlSlaveService = (new Mysql($slaveServer->getConnection()));
            $backupMasterService = new Backup($this->getConnection());
            $mysqlSlaveService->getConnection()->exec('mkdir -p /home/impero/impero/service/random');

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
             * Then we create backup file on master and transfer it to slave.
             * We sync slave to backup point and import file to slave.
             * When file is imported we refresh config and resume slave operations.
             *
             * @T00D00 - take down only $database, then sync only $database from binlog.
             *         - we need to make sure that no one touches mysql server in the meantime.
             */
            $mysqlSlaveService->stopSlave();

            /**
             * Create backup.
             */
            $backupFile = $backupMasterService->createMysqlBackup($this);

            if (!$backupFile) {
                throw new Exception('No backup file?');
            }

            /**
             * Resume slave until backup, then stop slave.
             */
            $this->syncSlaveUntilBackup($backupFile, $slaveServer);
            $mysqlSlaveService->stopSlave();

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
             * Update binlog update.
             * Dump new mysql config.
             */
            $databasesOnSlave = $slaveServer->slaveDatabases();
            $mysqlSlaveService->refreshSlaveReplicationFilter($databasesOnSlave);
            $mysqlSlaveService->dumpSlaveReplicationFilter($databasesOnSlave);

            /**
             * Start slave.
             */
            $mysqlSlaveService->startSlave();

            /**
             * Wait few seconds for slave to get in sync.
             * Then refresh cluster configuration.
             */
        });
    }

    /**
     * @param Server $slaveServer
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function dereplicateFrom(Server $slaveServer)
    {
        $task = Task::create('Dereplicating db #' . $this->id . ' from ' . $slaveServer->ip);

        $db = $this;
        $task->make(function() use ($slaveServer, $db) {
            $mysqlSlaveService = (new Mysql($slaveServer->getConnection()));

            $mysqlSlaveService->stopSlave();

            $databasesOnSlave = $slaveServer->slaveDatabases()->filter(function(Database $database) use ($db) {
                return $database->id != $db->id;
            });

            $mysqlSlaveService->refreshSlaveReplicationFilter($databasesOnSlave);
            $mysqlSlaveService->dumpSlaveReplicationFilter($databasesOnSlave);

            $mysqlSlaveService->startSlave();
        });
    }

    /**
     * @param $backupPath
     *
     * @throws Exception
     */
    public function syncSlaveUntilBackup($backupPath, Server $slaveServer)
    {
        $task = Task::create('Syncing slave server ' . $slaveServer->ip . ' until backup point');

        return $task->make(function() use ($backupPath, $slaveServer) {
            /**
             * Read binlog position and resume slave sync.
             * -- CHANGE MASTER TO MASTER_LOG_FILE='mysql-bin.002142', MASTER_LOG_POS=752877;
             */
            $positionLine = explode("\n", $this->server->exec('head ' . $backupPath))[0] ?? null;
            if (!$positionLine) {
                throw new Exception('Cannot parse binlog position from backup.');
            }

            /**
             * Validate
             */
            if (!strpos($positionLine, 'MASTER_LOG_FILE=') || !strpos($positionLine, 'MASTER_LOG_POS=')) {
                throw new Exception('No data provider for binlog position.');
            }

            /**
             * Parse.
             */
            $start = strpos($positionLine, 'MASTER_LOG_FILE') + strlen('MASTER_LOG_FILE=') + 1;
            $logFile = substr($positionLine, $start, strpos($positionLine, ',', $start + 1) - $start - 1);
            $start = strpos($positionLine, 'MASTER_LOG_POS') + strlen('MASTER_LOG_POS=');
            $logPosition = substr($positionLine, $start, -1);

            /**
             * Validate.
             */
            if (strpos($logFile, 'mysql-bin.') !== 0 || !$logPosition || (int)$logPosition != $logPosition) {
                throw new Exception('Cannot parse binlog position');
            }

            /**
             * Build commands.
             */
            $syncSql = 'START SLAVE UNTIL MASTER_LOG_FILE = \'' . $logFile . '\', MASTER_LOG_POS = ' . $logPosition;
            $waitSql = 'SELECT MASTER_POS_WAIT(\'' . $logFile . '\', ' . $logPosition . ');';

            /**
             * First execute slave sync command:
             * Then execute command that waits for slave to catch up with master. :)
             */
            $slaveServer->execSql($syncSql);
            $slaveServer->execSql($waitSql);
        });
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
     * @throws Exception
     */
    public function requireMysqlMasterReplication()
    {
        (new Mysql($this->getConnection()))->requireMysqlMasterReplication();
    }

}
