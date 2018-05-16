<?php namespace Impero\Services\Service\Connection;

/**
 * Class LocalConnection
 *
 * @package Impero\Services\Service\Connection
 */
class LocalConnection implements ConnectionInterface, Connectable
{

    /**
     * @param      $command
     * @param null $output
     * @param null $error
     *
     * @return string
     */
    public function exec($command, &$output = null, &$error = null)
    {
        return exec($command, $output, $error);
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
        return mkdir($dir, $mode, $recursive);
    }

    /**
     * @param $file
     * @param $content
     *
     * @return bool|int
     */
    public function saveContent($file, $content)
    {
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