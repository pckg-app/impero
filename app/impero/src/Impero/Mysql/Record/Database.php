<?php namespace Impero\Mysql\Record;

use Exception;
use Impero\Mysql\Entity\Databases;
use Impero\Servers\Record\Server;
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

        $keyFile = $this->server->getService(OpenSSL::class)->createRandomHashFile();

        /**
         * @T00D00 - key file needs to be associated with backup
         *         - how will we automate that?
         */
        $backupFile = $this->createBackup();

        $encryptedBackup = $this->server->compressAndEncryptFile($backupFile, $keyFile);

        /**
         * Transfer encrypted backup to safe location.
         *         - will we transfer backups to digital ocean spaces?
         */
        $this->server->deleteFile($encryptedBackup);
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
     */
    public function requireMysqlMasterReplication()
    {
        $this->server->requireMysqlMasterReplication();

        /**
         * Check on $server that replication is active.
         * Check that entry is found in /etc/mysql/conf.d/replication.cnf
         */
        $replicationFile = $this->getReplicationConfigLocation();
        $currentReplication = $this->server->readFile($replicationFile);
        $replications = explode("\n", $currentReplication);

        /**
         * Check for existence.
         */
        $line = 'binlog_do_db = ' . $this->name;
        if (in_array($line, $replications)) {
            return;
        }

        /**
         * Add to file if nonexistent.
         */
        $this->server->exec('sudo echo "' . $line . '" >> ' . $replicationFile);

        /**
         * Mysql does not have to be restarted, we can execute mysql.
         * We just need to collect all databases that are replicated to this server.
         */
        $this->server->refreshMasterReplicationFilter();
    }

    /**
     * @param Server $server
     *
     * @throws Exception
     * Enable replication on slave server.
     */
    public function replicateTo(Server $server)
    {
        $this->requireMysqlMasterReplication();
        $this->server->getService(Mysql::class)->requireMysqlSlaveReplication($this);
        $this->transferTo($server);

        /**
         * Check on $server that replication is active.
         * Check that entry is found in /etc/mysql/conf.d/replication.cnf
         */
        $replicationFile = $this->getReplicationConfigLocation();
        $currentReplication = $server->readFile($replicationFile);
        $replications = explode("\n", $currentReplication);

        /**
         * Check for existance.
         */
        $line = 'replicate-wild-do-table=' . $this->name . '.%';
        if (in_array($line, $replications)) {
            return;
        }

        /**
         * Add to file if nonexistent.
         */
        $server->exec('sudo echo "' . $line . '" >> ' . $replicationFile);

        /**
         * Mysql does not have to be restarted, we can execute mysql.
         * We just need to collect all databases that are replicated to this server.
         */
        $this->server->refreshSlaveReplicationFilter();
    }

    /**
     * @param Server $server
     *
     * @throws Exception
     */
    public function transferTo(Server $server)
    {
        /**
         * Generate new passphrase for backup service: private/mysql/backup/keys/$hash.
         * This key will be transfered from impero to master and from impero to slave.
         * It will be deleted immediately after we don't need it anymore.
         */
        $opensslService = $server->getService(OpenSSL::class);
        $mysqlService = $server->getService(Mysql::class);

        /**
         * This will create random 1024-4096 long key used for encryption saved as random file.
         */
        $keyFile = $opensslService->createRandomHashFile();

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
        $mysqlService->stopSlave();

        /**
         * Create backup.
         */
        $backupFile = $this->createBackup();

        /**
         * Resume slave until backup.
         */
        $this->syncSlaveUntilBackup($backupFile);

        $encryptedFile = $this->server->compressAndEncryptFile($backupFile, $keyFile);

        /**
         * Transfer backup.
         */
        $encryptedCopy = '/home/impero/.impero/service/backup/mysql/temp/' . sha1(microtime());
        $this->server->transferFile($encryptedFile, $encryptedCopy, $server);
        $this->server->deleteFile($encryptedFile);

        /**
         * Decrypt backup.
         */
        $compressedCopy = '/home/impero/.impero/service/backup/mysql/compressed/' . sha1(microtime());
        $server->decryptFile($encryptedCopy, $compressedCopy, $keyFile);
        $this->server->deleteFile($encryptedCopy);

        /**
         * Decompress file.
         */
        $backupCopy = '/home/impero/.impero/service/backup/mysql/temp/backups' . sha1(microtime());
        $server->decompressFile($compressedCopy, $backupCopy);
        $this->server->deleteFile($compressedCopy);

        /**
         * Import backup.
         */
        $this->importBackup($backupFile, $server);
        $this->server->deleteFile($backupFile);

        /**
         * Start slave.
         */
        $server->getMysqlService()->startSlave();

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
    public function syncSlaveUntilBackup($backupPath)
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

        $this->server->execSql($command);
    }

    /**
     * @return string
     */
    public function createBackup()
    {
        /**
         * This commands will always executed by impero user, which is always available on filesystem.
         *
         * @T00D00 - read password from .cnf in impero home dir?
         *         - make sure that backup path exists and is writable
         */
        $user = 'impero';
        $backupPath = '/home/impero/.impero/service/backup/mysql/backups/';
        $file = $this->name . '_' . date('Ymdhis') . '_' . $this->server_id . '.sql';
        $flags = '--routines --triggers --skip-opt --order-by-primary --create-options --compact --master-data=2 --single-transaction --extended-insert --add-locks --disable-keys';

        $dumpCommand = 'mysqldump ' . $flags . ' -u ' . $user . ' ' . $this->name . ' > ' . $backupPath . $file;
        $this->server->exec($dumpCommand);

        return $file;
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

}
