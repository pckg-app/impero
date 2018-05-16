<?php namespace Impero\Services\Service;

/**
 * Class PhpFpm
 *
 * @package Impero\Services\Service
 */
class PhpFpm extends Php
{

    /**
     * @var string
     */
    protected $service = 'php7.0-fpm';

    /**
     * @var string
     */
    protected $name = 'PHP-FPM';

    /**
     * @return bool|mixed|null|string
     */
    public function getVersion()
    {
        return null;
    }

}