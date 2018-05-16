<?php namespace Impero\Services\Service\Connection;

/**
 * Interface ConnectionInterface
 *
 * @package Impero\Services\Service\Connection
 */
interface ConnectionInterface
{

    /**
     * @return mixed
     */
    public function open();

    /**
     * @return mixed
     */
    public function close();

    /**
     * @param      $command
     * @param null $output
     * @param null $error
     *
     * @return mixed
     */
    public function exec($command, &$output = null, &$error = null);

    /**
     * @param $dir
     *
     * @return mixed
     */
    public function dirExists($dir);

    /**
     * @param $dir
     * @param $mode
     * @param $recursive
     *
     * @return mixed
     */
    public function createDir($dir, $mode, $recursive);

    /**
     * @param $file
     * @param $content
     *
     * @return mixed
     */
    public function saveContent($file, $content);

}