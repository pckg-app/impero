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
        $data = post(['username', 'password', 'server_id', 'database', 'privilege']);

        /**
         * Save user in our database.
         */
        $user = User::create(['name' => $data['username'], 'server_id' => $data['server_id']]);

        /**
         * Connect to proper mysql server and execute sql.
         */
        $server = Server::gets(['id' => $data['server_id']]);
        $database = Database::gets(['id' => $data['database'], 'server_id' => $data['server_id']]);

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
        $sql = null;
        if ($data['privilege'] !== 'admin') {
            $sql = 'GRANT ' . $permissions[$data['privilege']] . ' ON `' . $data['database'] . '`.* TO `' .
                   $data['username'] . '`@`localhost`';
        } else {
            $sql = 'GRANT ' . $permissions[$data['privilege']] . ' ON *.* TO `' . $data['username'] .
                   '`@`localhost` REQUIRE NONE WITH GRANT OPTION MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0';
        }

        $sql .= ' IDENTIFIED BY \'' . $data['password'] . '\'';

        d($sql);
        $mysqlConnection->execute($sql);

        return [
            'databaseUser' => $user,
        ];
    }

}
