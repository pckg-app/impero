<?php namespace Impero\Apache\Console;

use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Impero\Services\Service\Apache;
use Impero\Services\Service\HAProxy;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class DumpVirtualhosts extends Command
{

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('apache:dump')
             ->setDescription('Dump all virtualhosts')
             ->addOptions(
                 [
                     'server' => 'Server ID',
                 ],
                 InputOption::VALUE_REQUIRED
             )
             ->addOptions(
                 [
                     'dry' => 'Do not dump or restart',
                 ],
                 InputOption::VALUE_NONE
             );
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
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
         *
         * @T00D00 - run this in parallel
         */

        $this->output('Building apache');
        $virtualhosts = $server->getApacheConfig();

        $this->output('Building haproxy');
        $virtualhostsHaproxy = $server->getHaproxyConfig();

        $this->output('Dumping apache');
        $this->storeVirtualhosts($server, $virtualhosts);

        if ($server->getSettingValue('service.haproxy.active')) {
            $this->output('Dumping haproxy');
            $this->storeVirtualhostsHaproxy($server, $virtualhostsHaproxy);
        }

        $this->output('Done');
    }

    /**
     * @param Server $server
     * @param        $virtualhosts
     *
     * @throws \Exception
     */
    protected function storeVirtualhosts(Server $server, $virtualhosts)
    {
        $local = '/tmp/server.' . $server->id . '.virtualhosts';
        $remote = '/etc/apache2/sites-enabled/002-impero.conf';
        file_put_contents($local, $virtualhosts);
        if ($this->option('dry')) {
            return;
        }
        $this->outputDated('Dumping and restarting (apache)');
        $sshConnection = $server->getConnection();
        $sshConnection->sftpSend($local, $remote);
        unlink($local);

        /**
         * @T00D00 - check if apache is offline and apply previous configuration.
         */
        (new Apache($sshConnection))->restart();
    }

    /**
     * @param Server $server
     * @param        $virtualhosts
     */
    protected function storeVirtualhostsNginx(Server $server, $virtualhosts)
    {
        $local = '/tmp/server.' . $server->id . '.virtualhosts';
        $remote = '/etc/apache2/sites-enabled/002-impero.conf';
        file_put_contents($local, $virtualhosts);
        if ($this->option('dry')) {
            return;
        }
        $this->outputDated('Dumping and restarting (nginx)');
        $sshConnection = $server->getConnection();
        $sshConnection->sftpSend($local, $remote);
        unlink($local);

        /**
         * @T00D00 - check if apache is offline and apply previous configuration.
         */
        $sshConnection->exec('sudo service nginx restart');
    }

    /**
     * @param Server $server
     * @param        $virtualhosts
     *
     * @throws \Exception
     */
    protected function storeVirtualhostsHaproxy(Server $server, $virtualhosts)
    {
        $local = '/tmp/server.' . $server->id . '.haproxy';
        $remote = '/etc/haproxy/haproxy.cfg';
        file_put_contents($local, $virtualhosts);
        if ($this->option('dry')) {
            return;
        }
        $this->outputDated('Dumping and restarting (haproxy)');
        $sshConnection = $server->getConnection();
        $sshConnection->sftpSend($local, $remote);
        unlink($local);

        (new HAProxy($sshConnection))->restart();
    }

}