<?php namespace Impero\Mysql\Record;

use Impero\Mysql\Entity\Databases;
use Impero\Servers\Record\Server;
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

    public function replicate()
    {
        /**
         * Get current backup configuration.
         */
        $replicationFile = '/etc/mysql/conf.d/replication.cnf';
        $connection = $this->server->getConnection();
        $currentReplication = $connection->sftpRead($replicationFile);
        $replications = explode("\n", $currentReplication);

        /**
         * Check for existance.
         */
        $line = 'binlog_do_db = ' . $this->name;
        if (!in_array($line, $replications)) {
            /**
             * Add to file if nonexistent.
             */
            $connection->exec('sudo echo "' . $line . '" >> ' . $replicationFile);
            $connection->exec('sudo service mysql reload');
        }
    }

    /**
     * @param $data
     *
     * @return $this|Database
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
