<?php namespace Impero\Sites\Console;

use Impero\Apache\Record\Site;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class DestroySite extends Command
{

    public function configure()
    {
        $this->setName('site:destroy')->setDescription('Remove site from all servers')->addOptions([
                                                                                                       'site' => 'Site ID',
                                                                                                   ],
                                                                                                   InputOption::VALUE_REQUIRED);
    }

    public function handle()
    {
        $siteOption = $this->option('site');
        if (!$siteOption) {
            throw new \Exception('Site is required');
        }
        $site = Site::getOrFail($siteOption);

        if (!$this->askConfirmation('Do you really want to delete site #' . $site->id .
                                    ' - ' . $site->server_name . '? This action is not reversable!')) {
            $this->outputDated('Cancelling process');

            return;
        }

        $site->undeploy($this);
        /**
         * @T00D00 - delete site?
         */
        $this->outputDated('Undeployed');
    }

}