<?php namespace Impero\Services\Service\Connection;

use Exception;
use Impero\Servers\Record\Server;
use Throwable;

class SshConnection implements ConnectionInterface, Connectable
{

    /**
     * @var resource
     */
    protected $connection;

    protected $tunnel;

    protected $tunnelPort;

    protected $port;

    protected $user;

    protected $host;

    protected $key;

    /**
     * @var Server
     */
    protected $server;

    protected $ssh2Sftp = null;

    public function __construct(Server $server, $host, $user, $port, $key, $type = 'key')
    {
        $this->server = $server;

        $this->server->logCommand('Opening connection', null, null, null);

        $this->port = $port;
        $this->host = $host;
        $this->user = $user;
        $this->key = $key;
        /**
         * Create connection.
         */
        //d('connecting to ' . $host . ' : ' . $port);
        $this->connection = ssh2_connect($host, $port);

        if (!$this->connection) {
            $this->server->logCommand('Cannot open connection', null, null, null);
            throw new Exception('Cannot estamblish SSH connection');
        } else {
            $this->server->logCommand('Connection opened', null, null, null);
        }

        /**
         * Fingerprint check.
         */
        if ($type == 'key') {
            $keygen = null;
            $command = 'ssh-keygen -lf ' . $key . '.pub -E MD5';
            //d("command", $command);
            exec($command, $keygen);
            $keygen = $keygen[0] ?? null;
            $fingerprint = ssh2_fingerprint($this->connection, SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX);
            $publicKeyContent = file_get_contents($key . '.pub');
            $content = explode(' ', $publicKeyContent, 3);
            $calculated = join(':', str_split(md5(base64_decode($content[1])), 2));
            //d($calculated, $keygen, $fingerprint);

            if (!strpos($keygen, $calculated) || $fingerprint != $keygen) {
                //d("Wrong server fingerprint");
            }
        }

        /**
         * Authenticate with public and private key.
         */
        //d('authenticating ' . $user . ' with ' . $key);

        if ($type == 'key') {
            $auth = ssh2_auth_pubkey_file($this->connection, $user, $key . '.pub', $key, '');
        } else {
            $auth = ssh2_auth_password($this->connection, $user, $key);
        }

        /**
         * Throw exception on misconfiguration.
         */
        if (!$auth) {
            $this->server->logCommand('Cannot authenticate', null, null, null);
            throw new Exception("Cannot authenticate with key");
        } else {
            $this->server->logCommand('Authenticated with SSH', null, null, null);
        }
    }

    /**
     * @return Server
     */
    public function getServer()
    {
        return $this->server;
    }

    public function execMultiple($commands, &$errorStreamContent = null, $dir = null)
    {
        if (!$commands) {
            return $this;
        }

        foreach ($commands as $command) {
            $this->exec($command, $errorStreamContent, $dir);
        }

        return $this;
    }

    public function makeAndAllow($dir, $group = 'www-data', $permissions = 'g+rwx')
    {
        $this->exec('mkdir -p ' . $dir);
        $this->exec('chown www-data:www-data ' . $dir);
        $this->exec('chgrp ' . $group . ' ' . $dir);
        $this->exec('chmod ' . $permissions . ' ' . $dir);
    }

    public function exec($command, &$errorStreamContent = null, $dir = null)
    {
        $e = null;
        $infoStreamContent = null;
        $errorStreamContent = null;
        if ($dir) {
            $command = 'cd ' . $dir . ' && ' . $command;
        }
        $serverCommand = $this->server->logCommand('Executing command ' . $command, null, null, null);
        try {
            $stream = ssh2_exec($this->connection, $command);

            $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);

            stream_set_blocking($errorStream, true);
            stream_set_blocking($stream, true);

            $errorStreamContent = stream_get_contents($errorStream);
            $infoStreamContent = stream_get_contents($stream);
        } catch (Throwable $e) {
            $serverCommand->setAndSave(
                [
                    'command' => 'Error executing command ' . $command,
                    'info'    => $infoStreamContent,
                    'error'   => $errorStreamContent,
                ]
            );

            return null;
        } finally {
            $serverCommand->setAndSave(
                [
                    'command' => 'Command executed ' . $command,
                    'info'    => $infoStreamContent,
                    'error'   => $errorStreamContent,
                    'code'    => 1,
                ]
            );
        }

        return $infoStreamContent;
    }

    public function open()
    {

    }

    public function close()
    {
        if ($this->connection) {
            $this->server->logCommand('Closing connection', null, null, null);

            ssh2_exec($this->connection, 'exit');
            unset($this->connection);
        }

        return $this;
    }

    public function sftpSend($local, $remote, $mode = null, $isFile = true)
    {
        $this->server->logCommand('Copying local ' . $local . ' to remote ' . $remote, null, null, null);

        $sftp = $this->openSftp();

        $stream = fopen("ssh2.sftp://" . intval($sftp) . $remote, 'w');

        $ok = @fwrite($stream, $isFile ? file_get_contents($local) : $local);

        @fclose($stream);

        return !!$ok;
    }

    public function sftpRead($file)
    {
        /*return '[client]
password = s0m3p4ssw0rd';*/

        $this->server->logCommand('Reading remote ' . $file, null, null, null);

        $sftp = $this->openSftp();

        $stream = @fopen("ssh2.sftp://" . intval($sftp) . $file, 'r');

        if (!$stream) {
            throw new Exception('Cannot open stream');
        }

        $content = fread($stream, filesize("ssh2.sftp://" . intval($sftp) . $file));

        @fclose($stream);

        return $content;
    }

    protected function openSftp()
    {
        if (!$this->ssh2Sftp) {
            $this->ssh2Sftp = ssh2_sftp($this->connection);
        }

        return $this->ssh2Sftp;
    }

    public function tunnel()
    {
        if (!$this->tunnel) {
            $this->server->logCommand('Creating SSH tunnel', null, null, null);
            /**
             * Create SSH tunnel.
             * -p 22222 - connect via ssh on port 22222
             * -f - for connection, send it to background
             * -L localPort:ip:remotePort - local forwarding (-R - opposite, remote forwarding)
             */
            $this->tunnelPort = 3307; // @T00D00
            $command = 'ssh -p ' . $this->port . ' -i ' . $this->key . ' -f -L ' . $this->tunnelPort .
                ':127.0.0.1:3306 ' . $this->user . '@' . $this->host . ' sleep 10 >> /tmp/tunnel.' .
                $this->host . '.' . $this->port . '.log';
            shell_exec($command);
        }

        return $this->tunnelPort;
    }

    public function dirExists($dir)
    {
        $sftp = $this->openSftp();

        return is_dir("ssh2.sftp://" . intval($sftp) . $dir);
    }

    public function createDir($dir, $mode, $recursive)
    {
        $sftp = $this->openSftp();

        return ssh2_sftp_mkdir($sftp, $dir, $mode, $recursive);
    }

    public function fileExists($file)
    {
        $sftp = $this->openSftp();

        return file_exists("ssh2.sftp://" . intval($sftp) . $file) && !is_dir("ssh2.sftp://" . intval($sftp) . $file);
    }

    public function symlinkExists($symlink)
    {
        $sftp = $this->openSftp();

        return is_link("ssh2.sftp://" . intval($sftp) . $symlink);
    }

    public function rsyncCopyTo($file, Server $to)
    {
        $dir = implode('/', array_slice(explode('/', $file), 0, -1));
        if (!$to->getConnection()->dirExists($dir)) {
            $to->getConnection()->exec('mkdir -p ' . $dir);
        }
        $this->exec('rsync -a ' . $file . ' impero@' . $to->privateIp . ':' . $file);
    }

    public function rsyncCopyFrom($file, Server $from = null)
    {
        if (!$from) {
            /**
             * We are copying for example some file from impero to $this connection.
             */
            $command = 'rsync -a ' . $file . ' impero@' . $this->host . ':' . $file;

            /**
             * @T00D00 ... how to do this transparent?
             *         ... how to use different port?
             */
            exec($command);

            return;
        }
        /**
         * We are copying for example some file from $this connection to remote $from
         */
        $command = 'rsync -a impero@' . $from->privateIp . ':' . $file . ' ' . $file;
        $this->exec($command);
    }

    public function saveContent($file, $content)
    {
        /**
         * Save content to temporary file.
         */
        $tmp = tempnam('/tmp', 'tmp');
        file_put_contents($tmp, $content);

        /**
         * Send file to remote server.
         */
        $this->sftpSend($tmp, $file);

        /**
         * Remove temporary file.
         */
        unlink($tmp);
    }

    public function getConnection() : ConnectionInterface
    {
        return $this;
    }

}