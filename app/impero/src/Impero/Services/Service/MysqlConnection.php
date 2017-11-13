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
        $command = 'mysql -u impero -ps0m3p4ssw0rd -e' . escapeshellarg($sql . ';');

        $result = $this->sshConnection->exec($command, $error);

        return $result;
    }

    public function query($database, $sql, $binds = [])
    {
        if (!$this->pdo) {
            $tunnelPort = $this->sshConnection->tunnel();

            $this->pdo = new PDO(
                "mysql:host=127.0.0.1:" . $tunnelPort . ";charset=utf8;dbname=" . $database,
                'impero',
                's0m3p4ssw0rd'
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
        return $this->sshConnection->exec('mysql -u impero -ps0m3p4ssw0rd ' . ($database ? $database . ' ' : '')
                                          . '< ' . $pipe, $error);
    }

}