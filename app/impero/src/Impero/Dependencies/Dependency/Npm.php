<?php namespace Impero\Dependencies\Dependency;

class Npm extends AbstractDependency
{

    protected $dependency = 'npm';

    protected $dependencies = [
        Node::class,
    ];

    public function getVersion()
    {
        $response = $this->getConnection()->exec('npm --version');

        return $response;
    }

    public function getStatus()
    {
        $outdated = false;

        return $outdated ? 'outdated' : 'ok';
    }

}