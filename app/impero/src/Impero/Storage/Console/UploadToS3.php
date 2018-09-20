<?php namespace Impero\Storage\Console;

use Impero\Servers\Record\Server;
use Impero\Services\Service\DigitalOcean;
use Pckg\Framework\Console\Command;

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
                                                                                                        ]);
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

        $do = (new DigitalOcean($server->getConnection()));
        $do->uploadToSpaces($file);
    }

}