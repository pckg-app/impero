<?php namespace Impero\Services\Service;

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
        if (!$output) {
            $output = $this->prepareDirectory('random') . sha1random();
        }
        $command = 'zip ' . $output . ' ' . $file . ' && mv ' . $output . '.zip ' . $output;
        $this->exec($command);

        return $output;
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

        $command = 'zip ' . $output . ' -y -x "live/*/*/storage/tmp/*" -x "live/*/*/storage/cache/*" -r ' . $input .
            ' && mv ' . $output . '.zip ' . $output;
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
    public function decompressFile($file, $output = null)
    {
        if (!$output) {
            $output = $this->prepareDirectory('random') . sha1random();
        }
        $command = 'unzip ' . $file . ' ' . $output;
        $this->exec($command);

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