<?php namespace Impero\Servers\Console;

use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class CreateDroplet extends Command
{

    protected function configure()
    {
        $this->setName('droplet:create')
            ->setDescription('Create droplet on Digital Ocean and sync it with server')
            ->addOptions([
                'size' => 'Unique size ID',
                'datacenter' => 'Datecenter ID',
                'name' => 'Server name',
            ], InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {



    }

}