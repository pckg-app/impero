<?php namespace Impero\Apache\Console;

use Pckg\Framework\Console\Command;

class LetsEncryptRenew extends Command
{

    /**
     * List all sites that need to be renewed.
     */
    public function handle()
    {

    }

    protected function configure()
    {
        $this->setName('letsencrypt:renew')->setDescription('Renew LetsEncrypt certificates');
    }

}