<?php namespace Impero\Dependencies\Dependency;

class Less extends AbstractDependency
{

    protected $dependency = 'less';

    protected $via = 'npm';

    protected $dependencies = [
        Npm::class,
    ];

    public function getVersion()
    {
        $response = $this->getConnection()->exec('less version');

        return $response;
    }

    public function getStatus()
    {
        return 'ok';
    }

}