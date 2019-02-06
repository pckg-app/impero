<?php namespace Impero\Sites\Console;

use Impero\Apache\Record\Site;
use Impero\Servers\Record\Server;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class CheckoutSite extends Command
{

    public function configure()
    {
        $this->setName('site:checkout')->setDescription('Deploy services to server')->addOptions([
                                                                                                     'server' => 'Server ID',
                                                                                                     'site'   => 'Site ID',
                                                                                                 ],
                                                                                                 InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        $site = Site::gets($this->option('site'));
        $server = Server::gets($this->option('server'));

        $site->checkout($server);
    }

}