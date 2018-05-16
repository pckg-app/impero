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
            $output = $this->prepareDirectory('zip/compressed') . sha1random();
        }
        $command = 'zip ' . $file . ' ' . $output;
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
            $output = $this->prepareDirectory('zip/decompressed') . sha1random();
        }
        $command = 'unzip ' . $file . ' ' . $output;
        $this->exec($command);
        return $output;
    }

}