<?php namespace Impero\Services\Service;

use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;

/**
 * Class Rsync
 *
 * @package Impero\Services\Service
 */
class Rsync extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'rsync';

    /**
     * @var string
     */
    protected $name = 'Rsync';

    /**
     * @return mixed|null
     */
    public function getVersion()
    {
        return null;
    }

    public function copyTo(Server $to, $file)
    {
        $task = Task::create('Copying file ' . $file . ' to server #' . $to->privateId);

        return $task->make(function() use ($to, $file) {
            $command = 'rsync -a ' . $file . ' impero@' . $to->privateIp . ':' . $file;
            $this->exec($command);
        });
    }

}