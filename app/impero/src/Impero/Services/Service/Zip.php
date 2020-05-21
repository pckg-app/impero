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

        return $task->make(function () use ($file, $output) {
            if (!$output) {
                $output = $this->prepareDirectory('random') . sha1random();
            }
            $dir = collect(explode('/', $output))->slice(0, -1)->implode('/');
            $command = 'cd ' . $dir . ' && zip -j ' . $output . ' ' . $file . ' && mv ' . $output . '.zip ' . $output;
            $this->exec($command);

            return $output;
        });
    }

    /**
     * @param      $input
     * @param null $output
     *
     * @return null|string
     */
    public function compressDirectory($input, $output = null)
    {
        if (!$output) {
            $output = $this->prepareDirectory('random') . sha1random();
        }

        $command = 'sudo zip ' . $output . '.zip -y -x "**/storage/tmp/*" -x "**/storage/cache/*" -r ' . $input .
            ' && mv ' . $output . '.zip ' . $output;
        $this->exec($command);

        return $output;
    }

    /**
     * @param array $directories
     * @param null $output
     * @return string|null
     */
    public function compressDirectories(array $directories, $output = null)
    {
        return $this->compressDirectory(implode(" ", $directories), $output);
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
        $output = $this->exec('unzip -l ' . $file, $o, $e);
        $output = explode(' ', explode("\n", trim($output))[3] ?? '');
        $output = end($output);

        if (strlen($output) != 40) {
            throw new Exception("Cannot parse zip content");
        }

        $dir = $this->prepareDirectory('random');
        $this->exec('cd ' . $dir . ' && unzip -qq -u ' . $file);

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
        $command = 'unzip ' . $file . ' -d ' . $output;
        $this->exec($command);

        /**
         * In that directory there's a single file that needs to be unzipped.
         * Why?
         */
        $lsLa = $this->getConnection()->exec('ls -la ' . $output . ' | head -n 4', $outputs);
        $array = explode(" ", $outputs);
        $name = end($array);

        $command = 'cd ' . $output . ' && unzip ' . $output . '/' . $name;
        $this->exec($command);

        return $output;
    }

}