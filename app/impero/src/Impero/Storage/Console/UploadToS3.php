<?php namespace Impero\Storage\Console;

use Impero\Servers\Record\Server;
use Impero\Services\Service\S3cmd;
use Pckg\Framework\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class UploadToS3
 *
 * @package Impero\Storage\Console
 */
class UploadToS3 extends Command
{

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('storage:upload-to-s3')->setDescription('Upload file to S3 storage')->addOptions([
                                                                                                            'file'   => 'Full file path',
                                                                                                            'server' => 'Server ID',
                                                                                                        ],
                                                                                                        InputOption::VALUE_REQUIRED);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\FileExistsException
     * @throws \Exception
     */
    public function handle()
    {
        $file = $this->option('file');
        $server = Server::getOrFail($this->option('server'));

        $s3cmd = new S3cmd($server->getConnection());

        $s3cmd->put($file, 'backup/impero/000000' . sha1(microtime()));
    }

}