<?php namespace Impero\Apache\Console;

use Impero\Servers\Record\Server;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class DumpMysql extends Command
{

    public function handle()
    {
        /**
         * Master server.
         * Slave server.
         */
        $server = Server::getOrFail($this->option('server'));

        /**
         * Collect configuration.
         */
        $this->output('Collecting configuration');
        $configuration = $server->getMysqlConfig();

        /**
         * Dump files.
         */
        $sshConnection = $server->getConnection();
        foreach ($configuration as $file => $contents) {
            /**
             * Generate locally.
             */
            $localFile = '/tmp/server.' . $server->id . '.mysql.' . sha1($file);
            file_put_contents($localFile, $contents);

            /**
             * Skip sending when dry.
             */
            if ($this->option('dry')) {
                return;
            }

            /**
             * Dump file.
             */
            $this->outputDated('Dumping ' . $file);
            $sshConnection->sftpSend($localFile, $file);
            unlink($localFile);
        }

        /**
         * Done.
         */
        $this->output('Done.');
    }

    protected function configure()
    {
        $this->setName('mysql:dump')
             ->setDescription('Dump mysql configuration')
             ->addOptions([
                              'server' => 'Server ID',
                          ], InputOption::VALUE_REQUIRED)
             ->addOptions([
                              'dry' => 'Do not dump on remote',
                          ], InputOption::VALUE_NONE);
    }

}