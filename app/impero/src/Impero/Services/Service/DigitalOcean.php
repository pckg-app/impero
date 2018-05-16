<?php namespace Impero\Services\Services;

use Aws\S3\S3Client;
use Impero\Services\Service\AbstractService;
use Impero\Services\Service\ServiceInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;

class DigitalOcean extends AbstractService implements ServiceInterface
{

    public function getVersion()
    {
        // TODO: Implement getVersion() method.
    }

    /**
     * @param $file
     *
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\FileExistsException
     */
    public function uploadToSpaces($file)
    {
        /**
         * Create bucket config.
         */
        $config = only(
            config('impero.service.digitalocean.spaces', []), ['endpoint', 'key', 'secret', 'region', 'bucket']
        );
        $client = new S3Client(
            [
                'endpoint'    => $config['endpoint'],
                'version'     => 'latest',
                'credentials' => [
                    'key'    => $config['key'],
                    'secret' => $config['secret'],
                ],
                'region'      => $config['region'],
            ]
        );
        $adapter = new AwsS3Adapter($client, $config['bucket']);
        $filesystem = new Filesystem($adapter);

        /**
         * Transfer image to digital ocean spaces?
         */
        $stream = fopen($filesystem, 'r+');
        $coldName = 'backup/impero/' . filename($file);
        $filesystem->writeStream($coldName, $stream);

        return $coldName;
    }

}