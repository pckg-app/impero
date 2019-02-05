<?php namespace Impero\Ftp\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpFtpAccounts extends Command
{

    public function execute(InputInterface $input, OutputInterface $output)
    {
        die("DumpFtpAccounts::execute");
    }

    protected function configure()
    {
        $this->setName('ftp:dumpaccounts')->setDescription('Dump all FTP accounts');
    }

}