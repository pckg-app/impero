<?php namespace Impero\Mysql\Controller;

use Impero\Mysql\Record\Database;
use Impero\Mysql\Record\User;
use Impero\Servers\Record\Server;
use Pckg\Framework\Controller;

class DatabaseUserApi extends Controller
{

    public function postUserAction()
    {
        /**
         * Receive posted data.
         */
        $data = post(['username', 'password', 'server_id']);

        /**
         * Save user in our database.
         */
        User::create(['name' => $data['username'], 'server_id' => $data['server_id']]);

        /**
         * Connect to proper mysql server and execute sql.
         */
        $server = Server::gets(['id' => $data['server_id']]);
        $sshConnection = $server->getConnection();

        /**
         * Receive mysql connection?
         */
        $mysqlConnection = $sshConnection->getMysqlConnection();
        $sql = 'CREATE USER IF NOT EXISTS `' . $data['name'] . '`@`localhost` IDENTIFIED BY \'' . $data['password'] .
               '\'';
        $mysqlConnection->execute($sql);
    }

    public function postPrivilegesAction(User $databaseUser)
    {
        /**
         * Fetch posted data.
         */
        $databaseId = post('database');
        $database = Database::gets(['id' => $databaseId, 'server_id' => $databaseUser->server_id]);
        if (!$database) {

        }

        /**
         * Receive correct server.
         */
        $server = $databaseUser->server;

        /**
         * Get ssh and mysql connection.
         */
        $sshConnection = $server->getConnection();
        $mysqlConnection = $sshConnection->getMysqlConnection();

        /**
         * Permission mapper for simplified usage..
         */
        $permissions = [
            'client' => 'SELECT, UPDATE, DELETE, INSERT',
        ];

        /**
         * Connect to proper mysql server and execute sql.
         */
        $sql = 'GRANT ' . $permissions['client'] . ' ON `' . $database->name . '`.* TO `' . $databaseUser->name .
               '`@`localhost`';
        $mysqlConnection->execute($mysqlConnection);
    }

}
