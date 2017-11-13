<?php namespace Impero\Services\Service;

use Exception;
use PDO;

class MysqlConnection
{

    /**
     * @var SshConnection
     */
    protected $sshConnection;

    protected $tunnelPort;

    protected $pdo;

    public function __construct(SshConnection $sshConnection)
    {
        $this->sshConnection = $sshConnection;
    }

    public function execute($sql, &$error = null)
    {
        $command = 'mysql -u impero -e' . escapeshellarg($sql . ';');

        $result = $this->sshConnection->exec($command, $error);

        return $result;
    }

    protected function getMysqlPassword()
    {
        return parse_ini_string($this->sshConnection->sftpRead('/etc/mysql/conf.d/impero.cnf'))['password'];
    }

    public function query($database, $sql, $binds = [])
    {
        if (!$this->pdo) {
            $tunnelPort = $this->sshConnection->tunnel();

            $p = "mysql:host=127.0.0.1:" . $tunnelPort . ";charset=utf8;dbname=" . $database;
            $this->pdo = new PDO(
                $p,
                'impero',
                $this->getMysqlPassword()
            );
        }

        $prepared = $this->pdo->prepare($sql);
        $i = 0;
        foreach ($binds as $bind) {
            $prepared->bindValue($i, $bind);
            $i++;
        }

        $prepared->setFetchMode(\PDO::FETCH_OBJ);
        $execute = $prepared->execute();

        if (!$execute) {
            $errorInfo = $prepared->errorInfo();

            throw new Exception(
                'Cannot execute prepared statement: ' . end($errorInfo) . ' : ' . $prepared->queryString
            );
        }

        return $prepared->fetchAll();
    }

    public function pipeIn($pipe, $database = null, &$error = null)
    {
        return $this->sshConnection->exec('mysql -u impero ' . ($database ? $database . ' ' : '') . '< ' . $pipe,
                                          $error);
    }

}