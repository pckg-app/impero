<?php namespace Impero\Services\Service;

use Impero\Mysql\Record\Database;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
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
        // /etc/mysql/conf.d/impero.cnf
        $response = $this->getConnection()->exec('mysql -V');

        $start = strpos($response, 'Ver ') + strlen('Ver ');
        $end = strpos($response, ",");
        $length = $end - $start;

        return substr($response, $start, $length);
    }

    /**
     *
     */
    public function startSlave()
    {
        $task = Task::create('Starting slave');

        return $task->make(function() {
            return $this->getMysqlConnection()->execute('START SLAVE;');
        });
    }

    /**
     * @return MysqlConnection
     */
    public function getMysqlConnection()
    {
        return new MysqlConnection($this->getConnection());
    }

    /**
     *
     */
    public function stopSlave()
    {
        $task = Task::create('Stopping slave');

        return $task->make(function() {
            $this->getMysqlConnection()->execute('STOP SLAVE;');
        });
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
     * @return string
     */
    public function getReplicationConfigLocation()
    {
        return '/etc/mysql/conf.d/replication.cnf';
    }

    /**
     *
     */
    public function replicateMysqlSlave()
    {
        dd('Slave replication is not yet enabled?');
        $file = $this->getReplicationConfigLocation();
        $content = $this->getSlaveReplicationConfig();

        /**
         * Save changes and restart mysql server.
         */
        $this->getConnection()->sftpSend($file, $content);
        $this->getConnection()->exec('sudo service mysql restart');
    }

    public function getSlaveReplicationConfig()
    {
        return '[mysqld]
server-id = 2
log_bin = /var/log/mysql/mysql-bin.log
relay-log = /var/log/mysql/mysql-relay-bin.log';
    }

    public function getMasterReplicationConfig()
    {
        return '[mysqld]
server-id = 1
log_bin = /var/log/mysql/mysql-bin.log
expire_logs_days = 5
max_binlog_size = 100M';
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

        dd('replicating master?');
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
        $replications[] = 'max_binlog_size = 256M';

        /**
         * Save changes and restart mysql server.
         */
        $this->getConnection()->saveContent($replicationFile, implode("\n", $replications));
        $this->getConnection()->exec('sudo service mysql restart');
    }

    public function dumpSlaveReplicationFilter(Collection $databases)
    {
        $task = Task::create('Dumping mysql slave replication configuration');

        return $task->make(function() use ($databases) {
            $replicationFile = $this->getReplicationConfigLocation();
            $tables = $databases->map('name')->unique()->implode('.%,') . '.%';
            $content = '[mysqld]
#skip-slave-start
server-id = 2
relay-log = /var/log/mysql/mysql-relay-bin.log
log_bin = /var/log/mysql/mysql-bin.log
replicate-wild-do-table=' . $tables . '
';
            /**
             * Dump original slave filter and list of replicated tables.
             * If restart is needed that server is restarted manually.
             */
            $this->getConnection()->saveContent($replicationFile, $content);
        });
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

    /**
     * @param Collection $databases
     */
    public function refreshSlaveReplicationFilter(Collection $databases)
    {
        $task = Task::create('Refresing slave replication filter');

        return $task->make(function() use ($databases) {
            $dbString = $databases->map(function(Database $database) {
                return '\'' . $database->name . '.%\'';
            })->implode(',');
            $sql = 'CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE = (' . $dbString . ');';
            $this->getMysqlConnection()->execute($sql);
        });
    }

    public function syncBinlog(Server $to)
    {
        /**
         * Get last synced binlog location.
         */
        $startLog = 'mysql-bin.002904';
        $host = '10.135.61.34';
        $authFile = '/etc/mysql/conf.d/impero-zero.cnf';
        $resultDir = '/tmp/test-dump/';
        $command = 'mysqlbinlog --defaults-file=' . $authFile . ' --read-from-remote-server --host=' . $host .
            ' --raw --stop-never --result-file=' . $resultDir . ' ' . $startLog;

        /**
         * We've built a command, now we need to check that its properly configured in supervisor?
         * For example, we have a master (zero.gonparty.eu) and backup (one.gonparty.eu) servers.
         * We would like to copy binlog from master to backup server.
         * On master server we create backup user (impero) with privileges to connect from backup server.
         * On backup server we dump authentication details and protect file for access
         * (/etc/mysql/mysql.cnf/impero-mysqlbinlog-zero.gonparty.eu.cnf).
         * On backup server we configure supervisor service to make sure binlog is backed up at every time.
         */
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