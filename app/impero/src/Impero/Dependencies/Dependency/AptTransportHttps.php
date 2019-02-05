<?php namespace Impero\Dependencies\Dependency;

class AptTransportHttps extends AbstractDependency
{

    protected $dependency = 'apt-transport-https';

    public function getVersion()
    {
        $response = $this->getConnection()->exec('apt-transport-https version');

        return $response;
    }

    public function getStatus()
    {
        return 'ok';
    }

}