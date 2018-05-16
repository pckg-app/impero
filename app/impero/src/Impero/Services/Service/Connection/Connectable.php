<?php namespace Impero\Services\Service\Connection;

interface Connectable
{

    public function getConnection() : ConnectionInterface;

}