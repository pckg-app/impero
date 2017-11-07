<?php namespace Impero\Mysql\Controller;

use Impero\Mysql\Record\Database;
use Impero\Servers\Record\Server;
use Pckg\Framework\Controller;

class DatabaseApi extends Controller
{

    public function postDatabaseAction()
    {
        dd("saved!");
        /**
         * Receive posted data.
         */
        $data = post(['name', 'server_id']);
        d($data);

        /**
         * Save database in our database.
         */
        d('saving db');
        $database = Database::create(['name' => $data['name'], 'server_id' => $data['server_id']]);

        /**
         * Connect to proper mysql server and execute sql.
         */
        d('getting server');
        $server = Server::gets(['id' => $data['server_id']]);

        /**
         * Receive mysql connection?
         */
        d('getting mysql connection');
        $mysqlConnection = $server->getMysqlConnection();

        d('creating database');
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
