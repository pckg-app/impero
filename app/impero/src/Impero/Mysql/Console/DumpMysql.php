<?php namespace Impero\Apache\Console;

use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class DumpMysql extends Command
{

    protected function configure()
    {
        $this->setName('mysql:dump')
             ->setDescription('Dump mysql configuration')
             ->addOptions(
                 [
                     'server' => 'Server ID',
                 ],
                 InputOption::VALUE_REQUIRED
             );
    }

    public function handle()
    {
    }

}