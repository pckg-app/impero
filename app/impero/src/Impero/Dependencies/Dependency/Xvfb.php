<?php namespace Impero\Dependencies\Dependency;

class Xvfb extends AbstractDependency
{

    protected $dependency = 'xvfb';

    public function getVersion()
    {
        $response = $this->getConnection()
                         ->exec('xvfb version');

        return $response;
    }

    public function getStatus()
    {
        return 'ok';
    }

}