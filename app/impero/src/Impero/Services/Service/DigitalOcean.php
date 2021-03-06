<?php namespace Impero\Services\Service;

use Aws\S3\S3Client;
use Impero\Servers\Record\Task;
use Impero\Services\Service\Connection\LocalConnection;
use Impero\Services\Service\Connection\SshConnection;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;

/**
 * Class DigitalOcean
 *
 * @package Impero\Services\Services
 */
class DigitalOcean extends AbstractService implements ServiceInterface
{

    /**
     * @return mixed|void
     */
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
    public function uploadToSpaces($file, $delete = true)
    {
        /**
         * Create spaces filesystem.
         */
        $coldFilesystem = $this->getColdFilesystem();
        $connection = $this->getConnection();
        $coldName = 'backup/impero/' . filename($file);
        if ($connection instanceof LocalConnection) {
            $task = Task::create('Uploading to spaces via local connection');

            $task->make(function() use ($file, $coldFilesystem, $coldName, $connection, $delete) {
                /**
                 * Transfer image to digital ocean spaces?
                 * We need to be authenticated as
                 */
                $stream = fopen($file, 'r+');
                $coldFilesystem->writeStream($coldName, $stream);

                if ($delete) {
                    $connection->deleteFile($file);
                }
            });
        } elseif ($connection instanceof SshConnection) {
            $task = Task::create('Uploading to spaces via ssh connection');

            $task->make(function() use ($connection, $coldFilesystem, $coldName, $file, $delete) {
                /**
                 * @T00D00 - solve remote transfer (remote -> s3), it should be direct
                 *         the problem is, how to configure s3ctl script remotely?
                 *         or how to
                 */
                $connectionConfig = $connection->getConnectionConfig();
                $adapter = new SftpAdapter([
                                               'host'          => $connectionConfig['host'],
                                               'port'          => $connectionConfig['port'],
                                               'username'      => $connectionConfig['user'],
                                               'privateKey'    => $connectionConfig['key'],
                                               'password'      => null,
                                               'root'          => '/',
                                               'timeout'       => 10,
                                               'directoryPerm' => 0755,
                                           ]);
                $remoteFilesystem = new Filesystem($adapter);
                $coldFilesystem->writeStream($coldName, $remoteFilesystem->readStream($file));

                if ($delete) {
                    $connection->deleteFile($file);
                }
            });
        }

        return $coldName;
    }

    /**
     * @return Filesystem
     * @throws \InvalidArgumentException
     */
    public function getColdFilesystem()
    {
        /**
         * Create bucket config.
         */
        $config = only(config('impero.service.digitalocean.spaces', []),
                       ['endpoint', 'key', 'secret', 'region', 'bucket']);
        $client = new S3Client([
                                   'endpoint'    => $config['endpoint'],
                                   'version'     => 'latest',
                                   'credentials' => [
                                       'key'    => $config['key'],
                                       'secret' => $config['secret'],
                                   ],
                                   'region'      => $config['region'],
                               ]);
        $adapter = new AwsS3Adapter($client, $config['bucket']);
        $filesystem = new Filesystem($adapter);

        return $filesystem;
    }

    /**
     * @param $file
     *
     * @return string
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function downloadFromSpaces($coldName, $delete = false)
    {
        $connection = $this->getConnection();
        $connectionConfig = $connection->getConnectionConfig();

        $adapter = new SftpAdapter([
                                       'host'          => $connectionConfig['host'],
                                       'port'          => $connectionConfig['port'],
                                       'username'      => $connectionConfig['user'],
                                       'privateKey'    => $connectionConfig['key'],
                                       'password'      => null,
                                       'root'          => '/',
                                       'timeout'       => 10,
                                       'directoryPerm' => 0755,
                                   ]);
        $remoteFilesystem = new Filesystem($adapter);

        $coldFilesystem = $this->getColdFilesystem();
        $file = '/home/impero/impero/backup/impero/' . filename($coldName);
        d('writing to ' . $file);

        // $coldFilesystem->writeStream($coldName, $remoteFilesystem->readStream($file));
        if (!$remoteFilesystem->has($file)) {
            /**
             * This needs to be direct. Current implementation uses spaces-impero-destination.
             * We need to use spaces-destination connection.
             */
            $remoteFilesystem->writeStream($file, $coldFilesystem->readStream($coldName));
        } else {
            d('file already present');
        }

        /*if ($delete) {
            $connection->deleteFile($file);
        }*/

        return $file;

        $filesystem = $this->getFilesystem();

        $output = $this->prepareDirectory('random') . sha1random();
        $stream = $filesystem->readStream($file);
        $contents = stream_get_contents($stream);
        fclose($stream);
        file_put_contents($output, $contents);

        return $output;
    }

}