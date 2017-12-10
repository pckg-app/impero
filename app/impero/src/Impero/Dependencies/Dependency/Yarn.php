<?php namespace Impero\Dependencies\Dependency;

class Yarn extends AbstractDependency
{

    protected $dependency = 'yarn';

    public function getVersion()
    {
        $response = $this->getConnection()
                         ->exec('yarn version');

        return $response;
    }

    public function getStatus()
    {
        return 'ok';
    }

}