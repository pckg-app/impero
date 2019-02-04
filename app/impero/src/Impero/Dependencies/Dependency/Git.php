<?php namespace Impero\Dependencies\Dependency;

class Git extends AbstractDependency
{

    protected $dependency = 'git';

    public function getVersion()
    {
        $response = $this->getConnection()->exec('git --version');

        $versionStart = strlen('git version ');

        return substr($response, $versionStart);
    }

    public function getStatus()
    {
        $outdated = false;

        return $outdated ? 'outdated' : 'ok';
    }

}