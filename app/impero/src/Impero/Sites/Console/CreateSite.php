<?php namespace Impero\Sites\Console;

use Impero\Apache\Entity\Sites;
use Impero\Apache\Record\Site;
use Impero\Servers\Record\Server;
use Impero\User\Record\User;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class CreateSite extends Command
{

    public function configure()
    {
        $this->setName('site:create')->setDescription('Create and deploy site to server')->addOptions([
            'server' => 'Server ID',
        ],
            InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        $server = Server::gets($this->option('server'));

        $name = $this->askQuestion('Name:');
        $aliases = $this->askQuestion('Aliases:');
        $user = User::getOrFail(['email' => $this->askQuestion('User:')]);

        $site = Site::create([
            'server_name' => $name,
            'server_alias' => $aliases,
            'user_id' => $user->id,
            'error_log' => 1,
            'access_log' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'document_root' => $name,
            'server_id' => $server->id,
        ]);
    }

}