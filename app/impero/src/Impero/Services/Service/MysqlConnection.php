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
        d('executing', 'mysql -u impero -p s0m3p4ssw0rd -e\'' . $sql . ';\'');dd("executed");
        return $this->sshConnection->exec('mysql -u impero -p s0m3p4ssw0rd -e\'' . $sql . ';\'', $error);
    }

}