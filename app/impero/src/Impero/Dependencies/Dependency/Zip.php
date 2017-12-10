<?php namespace Impero\Dependencies\Dependency;

class Zip extends AbstractDependency
{

    protected $dependency = 'zip';

    public function getVersion()
    {
        $response = $this->getConnection()
                         ->exec('zip version');

        return $response;
    }

    public function getStatus()
    {
        return 'ok';
    }

}