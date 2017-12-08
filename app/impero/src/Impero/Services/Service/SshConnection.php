<?php namespace Impero\Services\Service;

use Exception;
use Throwable;

class SshConnection
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

    protected $server;

    public function __construct($server, $host, $user, $port, $key, $type = 'key')
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
            $serverCommand->setAndSave([
                                           'command' => 'Error executing command ' . $command,
                                           'info'    => $infoStreamContent,
                                           'error'   => $errorStreamContent,
                                       ]);

            return null;
        } finally {
            $serverCommand->setAndSave([
                                           'command' => 'Command executed ' . $command,
                                           'info'    => $infoStreamContent,
                                           'error'   => $errorStreamContent,
                                           'code'    => 1,
                                       ]);
        }

        return $infoStreamContent;
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

        $sftp = ssh2_sftp($this->connection);

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

        $sftp = ssh2_sftp($this->connection);

        $stream = fopen("ssh2.sftp://" . intval($sftp) . $file, 'r');

        $content = fread($stream, filesize("ssh2.sftp://" . intval($sftp) . $file));

        dd($content);
        return $content;
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

}