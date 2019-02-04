<?php namespace Impero\Dependencies\Dependency;

class Curl extends AbstractDependency
{

    protected $dependency = 'curl';

    protected $install = 'curl';

    protected $dependencies = [
        AptTransportHttps::class,
    ];

    public function getVersion()
    {
        $response = $this->getConnection()->exec('curl version');

        return $response;
    }

    public function getStatus()
    {
        return 'ok';
    }

}