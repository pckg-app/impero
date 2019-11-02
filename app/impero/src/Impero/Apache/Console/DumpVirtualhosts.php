<?php namespace Impero\Apache\Console;

use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Impero\Services\Service\Apache;
use Impero\Services\Service\HAProxy;
use Impero\Services\Service\Nginx;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class DumpVirtualhosts extends Command
{

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
        $server = (new Servers())->where('id', $this->option('server'))->withSettings()->oneOrFail();

        /**
         * Get server services: web and lb.
         *
         * @T00D00 - run this in parallel
         */

        $this->outputDated('Building apache');
        $virtualhosts = $server->getApacheConfig();

        $this->outputDated('Building haproxy');
        $virtualhostsHaproxy = $server->getHaproxyConfig();

        $this->outputDated('Building nginx');
        $virtualhostsNginx = $server->getNginxConfig();

        $this->outputDated('Dumping apache');
        $this->storeVirtualhosts($server, $virtualhosts);

        if ($server->getSettingValue('service.haproxy.active')) {
            $this->outputDated('Dumping haproxy');
            $this->storeVirtualhostsHaproxy($server, $virtualhostsHaproxy);
        }

        if ($server->getSettingValue('service.nginx.active')) {
            $this->outputDated('Dumping nginx');
            $this->storeVirtualhostsNginx($server, $virtualhostsNginx);
        }

        $this->outputDated('Done');
    }

    /**
     * @param Server $server
     * @param        $virtualhosts
     *
     * @throws \Exception
     */
    protected function storeVirtualhosts(Server $server, $virtualhosts)
    {
        /**
         * Virtualhosts.
         */
        $local = '/tmp/server.' . $server->id . '.virtualhosts';
        $remote = '/etc/apache2/sites-enabled/002-impero.conf';
        file_put_contents($local, $virtualhosts);

        /**
         * Ports.
         */
        $localPorts = '/tmp/server.' . $server->id . '.ports';
        $remotePorts = '/etc/apache2/ports.conf';
        $portsConfig = $server->getApachePortsConfig();
        file_put_contents($localPorts, $portsConfig);

        /**
         * Skip sending when dry.
         */
        if ($this->option('dry')) {
            return;
        }

        $this->outputDated('Dumping and restarting (apache)');
        $sshConnection = $server->getConnection();
        $sshConnection->sftpSend($local, $remote);
        $sshConnection->sftpSend($localPorts, $remotePorts);
        unlink($local);
        unlink($localPorts);

        /**
         * @T00D00 - check if apache is offline and apply previous configuration.
         */
        (new Apache($sshConnection))->restart();
    }

    /**
     * @param Server $server
     * @param        $virtualhosts
     *
     * @throws \Exception
     */
    protected function storeVirtualhostsHaproxy(Server $server, $virtualhosts)
    {
        /**
         * When HAProxy is running as service.
         */
        $type = 'service';
        $local = '/tmp/server.' . $server->id . '.haproxy';
        $remote = '/etc/haproxy/haproxy.cfg';
        file_put_contents($local, $virtualhosts);
        if ($this->option('dry')) {
            return;
        }
        if ($type === 'service') {
            $this->outputDated('Dumping and restarting (haproxy)');
            $sshConnection = $server->getConnection();
            $sshConnection->sftpSend($local, $remote);
            unlink($local);

            (new HAProxy($sshConnection))->reload();
        } elseif ($type === 'docker') {
            /**
             * When docker is already running, dump new config, and send SIGHUP signal.
             * Start it when it's not running.
             * Check for user, group and stats in config.
             * https://github.com/docker-library/haproxy/issues/6
             */
            $containerName = 'impero-service-entrypoint';
            $image = 'haproxy:1.8.21-alpine';
            $signal = 'sudo docker kill -s HUP ' . $containerName;
            $docker = 'sudo docker run --rm --net=host -v /etc/haproxy/haproxy.cfg.old:/usr/local' . $remote .
                ' -v /etc/haproxy/errors/:/etc/haproxy/errors/ --restart=on-failure --name ' . $containerName . ' ' .
                $image . ' -p 443:8012 -f /usr/local' . $remote;
        }
    }

    /**
     * @param Server $server
     * @param        $virtualhosts
     */
    protected function storeVirtualhostsNginx(Server $server, $virtualhosts)
    {
        /**
         * Virtualhosts.
         */
        $local = '/tmp/server.' . $server->id . '.nginx.virtualhosts';
        $remote = '/etc/nginx/sites-enabled/002-impero';
        file_put_contents($local, $virtualhosts);

        /**
         * Skip sending when dry.
         */
        if ($this->option('dry')) {
            return;
        }

        $this->outputDated('Dumping and restarting (nginx)');
        $sshConnection = $server->getConnection();
        $sshConnection->sftpSend($local, $remote);
        unlink($local);

        (new Nginx($sshConnection))->reload();
    }

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('apache:dump')
             ->setDescription('Dump all virtualhosts')
             ->addOptions([
                              'server' => 'Server ID',
                          ], InputOption::VALUE_REQUIRED)
             ->addOptions([
                              'dry' => 'Do not dump or restart',
                          ], InputOption::VALUE_NONE);
    }

}