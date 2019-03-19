<?php namespace Impero\Sites\Console;

use Impero\Apache\Record\Site;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class ExecSite extends Command
{

    public function configure()
    {
        $this->setName('site:exec')->setDescription('Exec command on site')->addOptions([
                                                                                            'site'    => 'Site ID',
                                                                                            'command' => 'Command to execute',
                                                                                        ], InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        $site = Site::getOrFail($this->option('site'));

        $this->outputDated('Executing');
        $site->getServerConnection()->exec($this->option('command'), $output, $error);
        d('output', $output, 'error', $error);
        $this->outputDated('Executed');
    }

}