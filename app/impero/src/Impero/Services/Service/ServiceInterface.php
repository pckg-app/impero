<?php namespace Impero\Services\Service;

/**
 * Interface ServiceInterface
 *
 * @package Impero\Services\Service
 */
interface ServiceInterface
{

    /**
     * @return mixed
     */
    public function getName();

    /**
     * @return mixed
     */
    public function getStatus();

    /**
     * @return mixed
     */
    public function getVersion();

}