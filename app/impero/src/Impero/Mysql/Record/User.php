<?php namespace Impero\Mysql\Record;

use Impero\Mysql\Entity\Users;
use Impero\Servers\Record\Server;
use Pckg\Database\Record;

class User extends Record
{

    protected $entity = Users::class;

    /**
     * Build edit url.
     *
     * @return string
     */
    public function getEditUrl()
    {
        return url('user.edit', ['user' => $this]);
    }

    /**
     * Build delete url.
     *
     * @return string
     */
    public function getDeleteUrl()
    {
        return url('user.delete', ['user' => $this]);
    }

    public function setUserIdByAuthIfNotSet()
    {
        if (!$this->user_id) {
            $this->user_id = auth()->user('id');
        }

        return $this;
    }

    public static function createFromPost($data)
    {
        /**
         * Save user in our database.
         */
        $user = static::create(['name' => $data['username'], 'server_id' => $data['server_id']]);

        /**
         * Connect to proper mysql server and execute sql.
         */
        $server = Server::gets(['id' => $data['server_id']]);
        $database = Database::gets(['id' => $data['database'], 'server_id' => $data['server_id']]);

        if (!$database) {
            throw new \Exception('Database with set id doesnt exist');
        }
        $data['database'] = $database['name'];

        /**
         * Get ssh and mysql connection.
         */
        $mysqlConnection = $server->getMysqlConnection();

        /**
         * Permission mapper for simplified usage..
         */
        $permissions = [
            'basic'    => 'SELECT, UPDATE, DELETE, INSERT',
            'advanced' => 'SELECT, UPDATE, DELETE, INSERT, ALTER, CREATE, INDEX, REFERENCES',
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

        $sql .= ' IDENTIFIED BY "' . $data['password'] . '"';

        $mysqlConnection->execute($sql);

        return $user;
    }

}
