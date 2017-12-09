<?php namespace Impero\Apache\Console;

use Impero\Apache\Entity\Sites;
use Impero\Apache\Record\Site;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class DumpVirtualhosts extends Command
{

    protected function configure()
    {
        $this->setName('apache:dump')
             ->setDescription('Dump all virtualhosts')
             ->addOptions(
                 [
                     'server' => 'Server ID',
                 ],
                 InputOption::VALUE_REQUIRED
             );
    }

    public function handle()
    {
        if ($this->option('server') != 2) {
            return;
        }

        $this->output('Building virtualhosts');
        $server = (new Servers())->where('id', $this->option('server'))->oneOrFail();
        $sites = (new Sites())->where('server_id', $server->id)->all();
        $virtualhosts = [];
        $sites->each(
            function(Site $site) use (&$virtualhosts) {
                $virtualhosts[] = $site->getVirtualhost();
            }
        );

        $virtualhosts = implode("\n\n", $virtualhosts);

        $this->output('Dumping virtualhosts');

        $this->storeVirtualhosts($server, $virtualhosts);

        $this->output('Virtualhosts were dumped, waiting for apache graceful');
    }

    protected function storeVirtualhosts(Server $server, $virtualhosts)
    {
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