<?php namespace Impero\Services\Service;

use Impero\Servers\Record\Server;
use Impero\Storage\Record\Storage;

/**
 * Class Cron
 *
 * @package Impero\Services\Service
 */
class Lsyncd extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'lsyncd';

    /**
     * @var string
     */
    protected $name = 'Lsyncd';

    /**
     * @return mixed|null
     */
    public function getVersion()
    {
        return null;
    }

    public function sync(Server $server, Storage $storage)
    {
        /**
         * @T00D00 - check that this command is ran only once
         */
        $command = 'lsyncd -rsyncssh ' . $storage->location . ' localhost /impero-bckp' . $storage->location;
        $this->exec($command);
    }

    public function restart()
    {
        $this->exec('sudo service lsyncd restart');
    }

}