<?php namespace Impero\Services\Service;

class MysqlConnection
{

    /**
     * @var SshConnection
     */
    protected $sshConnection;

    public function __construct(SshConnection $sshConnection)
    {
        $this->sshConnection = $sshConnection;
    }

    public function execute($sql, &$error = null)
    {
        $command = 'mysql -u impero -ps0m3p4ssw0rd -e' . escapeshellarg($sql . ';');

        $result = $this->sshConnection->exec($command, $error);

        d($command, $error);

        return $result;
    }

    public function pipeIn($pipe, $database = null, &$error = null)
    {
        return $this->sshConnection->exec('mysql -u impero -ps0m3p4ssw0rd ' . ($database ? $database . ' ' : '')
                                          . '< ' . $pipe, $error);
    }

}