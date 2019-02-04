<?php namespace Impero\Services\Service;

use Impero\Mysql\Record\Database;
use Impero\Servers\Record\Server;
use Pckg\Collection;

/**
 * Class Mysql
 *
 * @package Impero\Services\Service
 */
class Mysql extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'mysql';

    /**
     * @var string
     */
    protected $name = 'MySQL';

    /**
     * @return bool|mixed|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()->exec('mysql -V');

        $start = strpos($response, 'Ver ') + strlen('Ver ');
        $end = strpos($response, ",");
        $length = $end - $start;

        return substr($response, $start, $length);
    }

    /**
     * @return MysqlConnection
     */
    public function getMysqlConnection()
    {
        return new MysqlConnection($this->connection);
    }

    /**
     *
     */
    public function startSlave()
    {
        $this->getMysqlConnection()->execute('START SLAVE;');
    }

    /**
     *
     */
    public function stopSlave()
    {
        $this->getMysqlConnection()->execute('STOP SLAVE;');
    }

    /**
     * @return string
     */
    public function getReplicationConfigLocation()
    {
        return '/etc/mysql/conf.d/replication.cnf';
    }

    /**
     * @param Server $server
     *
     * @throws \Exception
     */
    public function requireMysqlSlaveReplication()
    {
        if ($this->isMysqlSlaveReplicated()) {
            return;
        }

        $this->replicateMysqlSlave();
    }

    /**
     *
     */
    public function replicateMysqlSlave()
    {
        dd('Slave replication is not yet enabled?');
        $file = $this->getReplicationConfigLocation();
        $lines[] = '[mysqld]';
        $lines[] = 'server-id = 2';
        $lines[] = 'relay-log = /var/log/mysql/mysql-relay-bin.log';
        $lines[] = 'log_bin = /var/log/mysql/mysql-bin.log';

        /**
         * Save changes and restart mysql server.
         */
        $this->getConnection()->sftpSend($file, implode("\n", $lines));
        $this->getConnection()->exec('sudo service mysql restart');
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isMysqlSlaveReplicated()
    {
        /**
         * Check that mysql is properly configured.
         */
        $file = $this->getReplicationConfigLocation();
        $replicationConfig = $this->getConnection()->sftpRead($file);
        $lines = explode("\n", $replicationConfig);

        return in_array('[mysqld]', $lines);
    }

    /**
     * @param Server $server
     *
     * @throws \Exception
     */
    public function requireMysqlMasterReplication()
    {
        if ($this->isMysqlMasterReplicated()) {
            return;
        }

        $this->replicateMysqlMaster();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isMysqlMasterReplicated()
    {
        /**
         * Get current backup configuration.
         */
        $replicationFile = $this->getReplicationConfigLocation();
        $currentReplication = $this->getConnection()->sftpRead($replicationFile);
        $replications = explode("\n", $currentReplication);

        return in_array('[mysqld]', $replications);
    }

    /**
     *
     */
    public function replicateMysqlMaster()
    {
        dd('Master replication is not yet enabled?');
        $replicationFile = $this->getReplicationConfigLocation();
        $replications[] = '[mysqld]';
        $replications[] = 'server-id = 1';
        $replications[] = 'log_bin = /var/log/mysql/mysql-bin.log';
        $replications[] = 'expire_logs_days = 5';
        $replications[] = 'max_binlog_size = 100M';

        /**
         * Save changes and restart mysql server.
         */
        $this->getConnection()->sftpSend($replicationFile, implode("\n", $replications));
        $this->getConnection()->exec('sudo service mysql restart');
    }

    /**
     * @param Collection $databases
     */
    public function refreshMasterReplicationFilter(Collection $databases)
    {
        $dbString = $databases->map(function(Database $database) {
            return '`' . $database->name . '`';
        })->implode(',');
        $sql = 'CHANGE REPLICATION FILTER REPLICATE_DO_DB = (' . $dbString . ');';
        $this->getMysqlConnection()->execute($sql);
    }

    /**
     * @param Collection $databases
     */
    public function refreshSlaveReplicationFilter(Collection $databases)
    {
        $dbString = $databases->map(function(Database $database) {
            return '`' . $database->name . '.%`';
        })->implode(',');
        $sql = 'CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE = (' . $dbString . ');';
        $this->getMysqlConnection()->execute($sql);
    }

    /**
     * @param Database $database
     *
     * @throws \Exception
     */
    public function isReplicatedOnMaster(Database $database)
    {
        $replicationFile = $this->getReplicationConfigLocation();
        $currentReplication = $this->getConnection()->sftpRead($replicationFile);
        $replications = explode("\n", $currentReplication);

        /**
         * Check for existence.
         */
        $line = 'binlog_do_db = ' . $database->name;

        return in_array($line, $replications);
    }

    /**
     * @param Database $database
     */
    public function replicateOnMaster(Database $database)
    {
        $replicationFile = $this->getReplicationConfigLocation();
        $line = 'binlog_do_db = ' . $database->name;

        /**
         * Add to file if nonexistent.
         */
        $this->getConnection()->exec('sudo echo "' . $line . '" >> ' . $replicationFile);

        /**
         * Mysql does not have to be restarted, we can execute mysql.
         * We just need to collect all databases that are replicated to this server.
         */
        $this->refreshMasterReplicationFilter($database->server->masterDatabases);
    }

    /**
     * @param Database $database
     *
     * @throws \Exception
     */
    public function isReplicatedOnSlave(Database $database)
    {
        /**
         * Check on $server that replication is active.
         * Check that entry is found in /etc/mysql/conf.d/replication.cnf
         */
        $replicationFile = $this->getReplicationConfigLocation();
        $currentReplication = $this->getConnection()->sftpRead($replicationFile);
        $replications = explode("\n", $currentReplication);

        /**
         * Check for existance.
         */
        $line = 'replicate-wild-do-table=' . $database->name . '.%';
        if (in_array($line, $replications)) {
            return;
        }
    }

    /**
     * @param Database $database
     */
    public function replicateOnSlave(Database $database)
    {
        /**
         * Add to file if nonexistent.
         */
        $line = 'replicate-wild-do-table=' . $database->name . '.%';
        $replicationFile = $this->getReplicationConfigLocation();
        $this->getConnection()->exec('sudo echo "' . $line . '" >> ' . $replicationFile);

        /**
         * Mysql does not have to be restarted, we can execute mysql.
         * We just need to collect all databases that are replicated to this server.
         *
         * @T00D00 - how to get all slave databases on this server?
         */
        $this->refreshSlaveReplicationFilter(new Collection());
    }

    public function syncBinlog(Server $to)
    {
        /**
         * Get last synced binlog location.
         */
        $lastBinLog = 'binlog.000999';
        $command = 'mysqlbinlog --read-from-remote-server --host=host_name --raw --stop-never ' . $lastBinLog;
    }

    public function syncDatabaseToBinlogLocation(Database $database, $binlogLocation)
    {
        $binlogs = 'binlog.001002 binlog.001003 binlog.001004';
        $startBinlogPosition = '27284';
        $stopBinlogPosition = '27284'; // or --stop-never ? what about --one-database ?
        $command = 'mysqlbinlog --start-position=' . $startBinlogPosition . '  --stop-position=' . $stopBinlogPosition .
            ' ' . $binlogs . ' ' . '| mysql --host=host_name -u root -p';
    }

}