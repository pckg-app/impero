<?php namespace Impero\Mysql\Controller;

use Impero\Mysql\Record\Database;
use Impero\Servers\Record\Server;
use Pckg\Framework\Controller;

class DatabaseApi extends Controller
{

    public function postDatabaseAction()
    {
        /**
         * Receive posted data.
         */
        $data = post(['name', 'server_id']);
dd($data);
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

        /**
         * Return created database.
         */
        return $this->response()->respondWithSuccess(['database' => $database]);
    }

    public function postBackupAction()
    {
        /**
         * Enable or disable mysql backup.
         */
    }

}
