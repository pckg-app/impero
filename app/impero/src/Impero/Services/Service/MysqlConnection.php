<?php namespace Impero\Services\Service;

use Exception;
use Impero\Services\Service\Connection\SshConnection;
use PDO;

/**
 * Class MysqlConnection
 *
 * @package Impero\Services\Service
 */
class MysqlConnection
{

    /**
     * @var SshConnection
     */
    protected $sshConnection;

    /**
     * @var
     */
    protected $tunnelPort;

    /**
     * @var
     */
    protected $pdo;

    /**
     * MysqlConnection constructor.
     *
     * @param SshConnection $sshConnection
     */
    public function __construct(SshConnection $sshConnection)
    {
        $this->sshConnection = $sshConnection;
    }

    /**
     * @param      $sql
     * @param null $error
     *
     * @return mixed
     */
    public function execute($sql, &$error = null, &$output = null)
    {
        $command = 'mysql -u impero -e ' . escapeshellarg($sql . ';');

        $result = $this->sshConnection->exec($command, $output, $error);

        return $result;
    }

    /**
     * @param       $database
     * @param       $sql
     * @param array $binds
     *
     * @return array
     * @throws Exception
     */
    public function query($database = null, $sql = null, $binds = [])
    {
        if (!$this->pdo) {
            /**
             * This is needed when we want to connect to remote mysql using ssh connection.
             * It is something that impero does to execute SQL queries with PDO in remote database.
             * For example, Impero can show tables form zero's db.
             * Do we want to utilize this in containers?
             */
            $tunnelPort = $this->sshConnection->tunnel();

            $p = "mysql:host=127.0.0.1:" . $tunnelPort . ";charset=utf8" . ($database ? ";dbname=" . $database : '');
            $this->pdo = new PDO($p, 'impero', $this->getMysqlPassword());
        }

        if (!$sql) {
            return null;
        }

        $prepared = $this->pdo->prepare($sql);
        $i = 1;
        foreach ($binds as $bind) {
            $prepared->bindValue($i, $bind);
            $i++;
        }

        $prepared->setFetchMode(\PDO::FETCH_OBJ);
        $execute = $prepared->execute();

        if (!$execute) {
            $errorInfo = $prepared->errorInfo();

            throw new Exception('Cannot execute prepared statement: ' . end($errorInfo) . ' : ' .
                                $prepared->queryString);
        }

        return $prepared->fetchAll();
    }

    /**
     * @return mixed
     */
    protected function getMysqlPassword()
    {
        $content = $this->sshConnection->sftpRead('/etc/mysql/conf.d/impero.cnf');

        return parse_ini_string($content)['password'];
    }

    /**
     * @param      $pipe
     * @param null $database
     * @param null $error
     *
     * @return mixed
     */
    public function pipeIn($pipe, $database = null, &$error = null, &$output = null)
    {
        return $this->sshConnection->exec('mysql -u impero ' . ($database ? $database . ' ' : '') . '< ' . $pipe,
                                          $output, $error);
    }

}