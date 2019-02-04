<?php namespace Impero\Services\Service;

use Exception;
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
    public function execute($sql, &$error = null)
    {
        $command = 'mysql -u impero -e' . escapeshellarg($sql . ';');

        $result = $this->sshConnection->exec($command, $error);

        return $result;
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
     * @param       $database
     * @param       $sql
     * @param array $binds
     *
     * @return array
     * @throws Exception
     */
    public function query($database, $sql, $binds = [])
    {
        if (!$this->pdo) {
            $tunnelPort = $this->sshConnection->tunnel();

            $p = "mysql:host=127.0.0.1:" . $tunnelPort . ";charset=utf8;dbname=" . $database;
            $this->pdo = new PDO($p, 'impero', $this->getMysqlPassword());
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
     * @param      $pipe
     * @param null $database
     * @param null $error
     *
     * @return mixed
     */
    public function pipeIn($pipe, $database = null, &$error = null)
    {
        return $this->sshConnection->exec('mysql -u impero ' . ($database ? $database . ' ' : '') . '< ' . $pipe,
                                          $error);
    }

}