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
        return ['database' => $database];
    }

    public function postImportFileAction(Database $database)
    {
        $server = $database->server;
        $file = post('file');
        $mysqlConnection = $server->getMysqlConnection();
        $mysqlConnection->pipeIn($file, $database->name);

        return $this->response()->respondWithSuccess();
    }

    public function postSearchAction()
    {
        $server = post('server');
        $databaseName = post('name');

        $database = Database::gets([
                                       'server_id' => $server,
                                       'name'      => $databaseName,
                                   ]);

        return [
            'database' => $database,
        ];
    }

    public function postQueryAction(Database $database)
    {
        $server = $database->server;
        $sql = post('sql');
        $bind = post('bind');
        $mysqlConnection = $server->getMysqlConnection();

        $data = $mysqlConnection->query($database->name, $sql, $bind);

        return [
            'data' => $data,
        ];
    }

    public function postBackupAction()
    {
        /**
         * Enable or disable mysql backup.
         */
    }

}
