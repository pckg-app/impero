<?php namespace Impero\Services\Service\Connection;

use Impero\Servers\Record\Server;

/**
 * Class LocalConnection
 *
 * @package Impero\Services\Service\Connection
 */
class LocalConnection implements ConnectionInterface, Connectable
{

    /**
     * @var null
     */
    protected $ssh2Sftp = null;

    /**
     * @param      $command
     * @param null $output
     * @param null $error
     *
     * @return string
     */
    public function exec($command, &$output = null, &$error = null)
    {
        d('exec local', $command);
        $return = exec($command .' 2>&1', $output, $error);
        d('output', $output, 'error', $error);
        return $return;
    }

    /**
     * @param $dir
     *
     * @return bool
     */
    public function dirExists($dir)
    {
        return is_dir($dir);
    }

    /**
     * @param $dir
     * @param $mode
     * @param $recursive
     *
     * @return bool
     */
    public function createDir($dir, $mode, $recursive)
    {
        d('creating local', $dir);
        $mode = 0755;
        try {
            $ok = mkdir($dir, $mode, $recursive);
            return $ok;
        } catch (\Throwable $e) {
            dd($dir, exception($e));
            throw $e;
            return false;
        }
    }

    public function deleteFile($file)
    {
        d('deleting local', $file);
        //unlink($file);
    }

    public function sendFileTo($local, $remote, Server $to)
    {
        try {
            $to->getConnection()->sftpSend($local, $remote);
        } catch (\Throwable $e) {
            dd(exception($e));
        }
    }

    /**
     * @param $file
     * @param $content
     *
     * @return bool|int
     */
    public function saveContent($file, $content)
    {
        d('saving local content', $content);
        $dir = implode('/', array_slice(explode('/', $file), 0, -1));
        if (!is_dir($dir)) {
            d('creating local dir', $dir);
            mkdir($dir, 777, true);
        }
        return file_put_contents($file, $content);
    }

    /**
     *
     */
    public function open()
    {
        /**
         * No need to open local connection.
         */
    }

    /**
     *
     */
    public function close()
    {
        /**
         * No need to close local connection.
         */
    }

    /**
     * @return ConnectionInterface
     */
    public function getConnection() : ConnectionInterface
    {
        return $this;
    }

}