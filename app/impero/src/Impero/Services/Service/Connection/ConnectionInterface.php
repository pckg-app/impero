<?php namespace Impero\Services\Service\Connection;

interface ConnectionInterface
{

    public function open();

    public function close();

    public function exec($command, &$output = null, &$error = null);

    public function dirExists($dir);

    public function createDir($dir, $mode, $recursive);

    public function saveContent($file, $content);

}