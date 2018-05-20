<?php namespace Impero\Services\Service\Connection;

/**
 * Interface Connectable
 *
 * @package Impero\Services\Service\Connection
 */
interface Connectable
{

    /**
     * @return ConnectionInterface|Connectable
     */
    public function getConnection();

}