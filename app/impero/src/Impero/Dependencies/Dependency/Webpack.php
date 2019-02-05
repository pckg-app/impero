<?php namespace Impero\Dependencies\Dependency;

class Webpack extends AbstractDependency
{

    protected $dependency = 'webpack';

    protected $via = 'npm';

    protected $dependencies = [
        Npm::class,
    ];

    public function getVersion()
    {
        $response = $this->getConnection()->exec('webpack version');

        return $response;
    }

    public function getStatus()
    {
        return 'ok';
    }

}