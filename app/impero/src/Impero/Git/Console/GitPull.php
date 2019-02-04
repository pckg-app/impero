<?php namespace Impero\Git\Console;

use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class GitPull extends Command
{

    protected function configure()
    {
        $this->setName('git:pull')
             ->setDescription('Pull latest changes from GIT repository')
             ->addOption('data', null, InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        $this->output('Pulling changes.');
        $data = json_decode($this->option('data'));

        $this->exec([
                        'cd ' . $data->dir . ' && git pull --ff',
                        'cd ' . $data->dir . ' && git submodule update',
                        'cd ' . $data->dir . ' && composer install',
                    ]);

        $this->output('Changes pulled.');
    }

}