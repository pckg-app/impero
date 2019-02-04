<?php namespace Impero\Dependencies\Dependency;

class Bower extends AbstractDependency
{

    protected $dependency = 'bower';

    public function getVersion()
    {
        $response = $this->getConnection()->exec('bower --version');

        return $response;
    }

    public function getStatus()
    {
        $outdated = false;

        return $outdated ? 'outdated' : 'ok';
    }

}