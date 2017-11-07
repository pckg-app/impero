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

        /**
         * Receive mysql connection?
         */
        $mysqlConnection = $server->getMysqlConnection();
        $sql = 'CREATE USER IF NOT EXISTS `' . $data['username'] . '`@`localhost` IDENTIFIED BY \'' . $data['password'] .
               '\'';
        $mysqlConnection->execute($sql);
    }

    public function postPrivilegesAction(User $databaseUser)
    {
        /**
         * Fetch posted data.
         */
        $databaseId = post('database');
        $privilege = post('privilege');
        $database = Database::gets(['id' => $databaseId, 'server_id' => $databaseUser->server_id]);
        if (!$database) {
            dd("no db");
        }

        /**
         * Receive correct server.
         */
        $server = $databaseUser->server;

        /**
         * Get ssh and mysql connection.
         */
        $mysqlConnection = $server->getMysqlConnection();

        /**
         * Permission mapper for simplified usage..
         */
        $permissions = [
            'basic'    => 'SELECT, UPDATE, DELETE, INSERT', // REFERENCES?
            'advanced' => 'SELECT, UPDATE, DELETE, INSERT, ALTER, CREATE TABLE, INDEX',
            'dump'     => 'SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER',
            'admin'    => 'SELECT, CREATE, DROP, RELOAD, SHOW DATABASES, CREATE USER',
        ];

        /**
         * Connect to proper mysql server and execute sql.
         */
        $mysqlConnection->execute('REVOKE ALL PRIVILEGES ON *.* FROM \'impero\'@\'localhost\';');

        $sql = null;
        if ($privilege !== 'admin') {
            $sql = 'GRANT ' . $permissions[$privilege] . ' ON `' . $database->name . '`.* TO `' . $databaseUser->name .
                   '`@`localhost`';
        } else {
            $sql = 'GRANT ' . $permissions[$privilege] . ' ON *.* TO `' . $databaseUser->name .
                   '`@`localhost` REQUIRE NONE WITH GRANT OPTION MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0';
        }

        $mysqlConnection->execute($sql);
    }

}
