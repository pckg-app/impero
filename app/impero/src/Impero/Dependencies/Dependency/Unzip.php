<?php namespace Impero\Dependencies\Dependency;

class Unzip extends AbstractDependency
{

    protected $dependency = 'unzip';

    public function getVersion()
    {
        $response = $this->getConnection()->exec('unzip version');

        return $response;
    }

    public function getStatus()
    {
        return 'ok';
    }

}