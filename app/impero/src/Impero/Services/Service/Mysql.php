<?php namespace Impero\Services\Service;

use Impero\Mysql\Record\Database;
use Impero\Servers\Record\Server;

class Mysql extends AbstractService implements ServiceInterface
{

    protected $service = 'mysql';

    protected $name = 'MySQL';

    public function getVersion()
    {
        $response = $this->getConnection()
            ->exec('mysql -V');

        $start = strpos($response, 'Ver ') + strlen('Ver ');
        $end = strpos($response, ",");
        $length = $end - $start;

        return substr($response, $start, $length);
    }

    public function getMysqlConnection()
    {
        return new MysqlConnection($this->connection);
    }

    public function startSlave()
    {
        $this->getMysqlConnection()->execute('START SLAVE;');
    }

    public function stopSlave()
    {
        $this->getMysqlConnection()->execute('STOP SLAVE;');
    }

    public function getReplicationConfigLocation()
    {
        return '/etc/mysql/conf.d/replication.cnf';
    }

    /**
     * @param Server $server
     *
     * @throws \Exception
     */
    public function requireMysqlSlaveReplication(Server $server)
    {
        /**
         * Check that mysql is properly configured.
         */
        $file = $this->getReplicationConfigLocation();
        $replicationConfig = $server->readFile($file);
        $lines = explode("\n", $replicationConfig);

        if (!in_array('[mysqld]', $lines)) {
            $lines[] = '[mysqld]';
            $lines[] = 'server-id = 2';
            $lines[] = 'relay-log = /var/log/mysql/mysql-relay-bin.log';
            $lines[] = 'log_bin = /var/log/mysql/mysql-bin.log';

            /**
             * Save changes and restart mysql server.
             */
            $server->writeFile($file, implode("\n", $lines));
            $this->getConnection()->exec('sudo service mysql restart');
        }
    }

    /**
     * @param Server $server
     *
     * @throws \Exception
     */
    public function requireMysqlMasterReplication(Server $server)
    {
        /**
         * Get current backup configuration.
         */
        $replicationFile = $this->getReplicationConfigLocation();
        $currentReplication = $server->readFile($replicationFile);
        $replications = explode("\n", $currentReplication);

        if (!in_array('[mysqld]', $replications)) {
            $replications[] = '[mysqld]';
            $replications[] = 'server-id = 1';
            $replications[] = 'log_bin = /var/log/mysql/mysql-bin.log';
            $replications[] = 'expire_logs_days = 5';
            $replications[] = 'max_binlog_size = 100M';

            /**
             * Save changes and restart mysql server.
             */
            $server->writeFile($replicationFile, implode("\n", $replications));
            $server->exec('sudo service mysql restart');
        }
    }

    public function refreshMasterReplicationFilter(Server $server)
    {
        $dbString = $server->masterDatabases->map(function (Database $database) {
            return '`' . $database->name . '`';
        })->implode(',');
        $sql = 'CHANGE REPLICATION FILTER REPLICATE_DO_DB = (' . $dbString . ');';
        $this->getMysqlConnection()->execute($sql);
    }

    public function refreshSlaveReplicationFilter(Server $server)
    {
        $dbString = $server->slaveDatabases->map(function (Database $database) {
            return '`' . $database->name . '.%`';
        })->implode(',');
        $sql = 'CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE = (' . $dbString . ');';
        $this->getMysqlConnection()->execute($sql);
    }

}