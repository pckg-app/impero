<?php namespace Impero\Services\Service;

class Zip extends AbstractService implements ServiceInterface
{

    protected $service = 'zip';

    protected $name = 'ZIP';

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
            $output = $this->prepareDirectory('zip/compressed') . $this->prepareRandomFile();
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
            $output = $this->prepareDirectory('zip/decompressed') . $this->prepareRandomFile();
        }
        $command = 'unzip ' . $file . ' ' . $output;
        $this->exec($command);
        return $output;
    }

}