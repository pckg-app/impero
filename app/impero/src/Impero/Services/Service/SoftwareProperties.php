<?php namespace Impero\Services\Service;

/**
 * Class SoftwareProperties
 *
 * @package Impero\Services\Service
 */
class SoftwareProperties extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'software-properties-common';

    /**
     * @var string
     */
    protected $name = 'Software properties (common)';

}