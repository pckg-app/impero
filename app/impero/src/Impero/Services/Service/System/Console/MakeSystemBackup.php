<?php namespace Impero\Services\Service\System\Console;

use Exception;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\Cron\Fork;
use Throwable;

/**
 * Class MakeMysqlBackup
 *
 * @package Impero\Services\Service\Backup\Console
 */
class MakeSystemBackup extends Command
{

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('service:system:backup')
             ->setDescription('Make backup of other system service configuration files');
    }

    /**
     *
     */
    public function handle()
    {
        $servers = (new Servers())->all();

        $servers->each(function(Server $server) {
            try {
                Fork::fork(function() use ($server) {
                    /**
                     * Throw backup event for all services that are listening for backup event. :)
                     */
                }, function() use ($server) {
                    return 'impero:backup:system:' . $server->id;
                }, function() {
                    throw new Exception('Cannot run system backup in parallel');
                });
            } catch (Throwable $e) {
                $this->output('EXCEPTION: ' . exception($e));
            }
        });
    }

}