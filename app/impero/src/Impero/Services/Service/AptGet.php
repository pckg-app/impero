<?php namespace Impero\Services\Service;

use Impero\Servers\Record\Task;

/**
 * Class AptGet
 *
 * @package Impero\Services\Service
 */
class AptGet extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'apt-get';

    /**
     * @var string
     */
    protected $name = 'AptGet';

    public function update()
    {
        $task = Task::create('Updating apt-get ');

        return $task->make(function() {
            $command = 'apt-get update';
            $this->exec($command);
        });
    }

    public function install($packages)
    {
        $task = Task::create('Installing apt packages ' . $packages);

        return $task->make(function() use ($packages) {
            $command = 'apt-get install -y ' . $packages;
            $this->exec($command);
        });
    }

    public function addRepository($repository)
    {
        $task = Task::create('Adding apt repository ' . $repository);

        return $task->make(function() use ($repository) {
            $command = 'LC_ALL=C.UTF-8 apt-add-repository ' . $repository . ' >> /dev/null';
            $this->exec($command);
        });
    }

}