<?php namespace Impero\Services\Service;

use Exception;
use Impero\Servers\Record\Task;

/**
 * Class Zip
 *
 * @package Impero\Services\Service
 */
class Zip extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'zip';

    /**
     * @var string
     */
    protected $name = 'ZIP';

    /**
     * @return string
     */
    public function getVersion()
    {
        return '@t00d00 version';
    }

    /**
     * @param      $file
     * @param null $output
     *
     * @return null|string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function compressFile($file, $output = null)
    {
        $task = Task::create('Compressing file ');

        return $task->make(
            function() use ($file, $output) {
                if (!$output) {
                    $output = $this->prepareDirectory('random') . sha1random();
                }
                $dir = collect(explode('/', $output))->slice(0, -1)->implode('/');
                $command = 'cd ' . $dir . ' && zip -j ' . $output . ' ' . $file . ' && mv ' . $output . '.zip ' . $output;
                $this->exec($command);
                return $output;
            }
        );
    }

    /**
     * @param      $input
     * @param null $output
     *
     * @return null|string
     */
    public function compressDirectory($input, $output = null)
    {
        if ($output) {
            $output = $this->prepareDirectory('random') . sha1random();
        }

        $command = 'zip ' . $output . ' -y -x "live/*/*/storage/tmp/*" -x "live/*/*/storage/cache/*" -r '
            . $input . ' && mv ' . $output . '.zip ' . $output;
        $this->exec($command);

        return $output;
    }

    /**
     * @param      $file
     * @param null $output
     *
     * @return null|string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function decompressFile($file)
    {
        $output = $this->exec('unzip -l ' . $file);
        $output = explode(' ', explode("\n", trim($output))[3] ?? '');
        $output = end($output);

        if (strlen($output) != 40) {
            throw new Exception("Cannot parse zip content");
        }

        $dir = $this->prepareDirectory('random');
        $this->exec('cd ' . $dir . ' && unzip -qq ' . $file);

        $output = $dir . $output;

        return $output;
    }

    /**
     * @param      $file
     * @param null $output
     *
     * @return null|string
     */
    public function decompressDirectory($file, $output = null)
    {
        if (!$output) {
            $output = $this->prepareDirectory('random') . sha1random();
        }
        $command = 'unzip ' . $file . ' ' . $output;
        $this->exec($command);
        return $output;
    }

}