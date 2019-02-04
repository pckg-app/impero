<?php namespace Impero\Apache\Console;

use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class DumpMysql extends Command
{

    protected function configure()
    {
        $this->setName('mysql:dump')->setDescription('Dump mysql configuration')->addOptions([
                                                                                                 'server' => 'Server ID',
                                                                                             ],
                                                                                             InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        return;
        if (!$this->option('server')) {
            $this->output('No server selected');

            return;
        }

        /**
         * Get server.
         */
        $server = (new Servers())->where('id', $this->option('server'))->oneOrFail();

        /**
         * Get server services: web and lb.
         */

        $this->output('Building mysql');
        $mysqlConfig = $server->getMysqlConfig();

        $this->output('Dumping apache');
        $this->storeMysqlConfig($server, $mysqlConfig);

        $this->output('Done');
    }

    protected function storeMysqlConfig(Server $server, $virtualhosts)
    {
        return;
        $local = '/tmp/server.' . $server->id . '.virtualhosts';
        $remote = '/etc/apache2/sites-enabled/002-impero.conf';
        file_put_contents($local, $virtualhosts);
        $sshConnection = $server->getConnection();
        $sshConnection->sftpSend($local, $remote);
        unlink($local);

        /**
         * @T00D00 - check if apache is offline and apply previous configuration.
         */
        $sshConnection->exec('sudo service apache2 graceful');
    }

}