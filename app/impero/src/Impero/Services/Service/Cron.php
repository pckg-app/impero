<?php namespace Impero\Services\Service;

/**
 * Class Cron
 *
 * @package Impero\Services\Service
 */
class Cron extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'cron';

    /**
     * @var string
     */
    protected $name = 'Cron';

    /**
     * @return mixed|null
     */
    public function getVersion()
    {
        return null;
    }

}