<?php namespace Impero\Dependencies\Dependency;

class Composer extends AbstractDependency
{

    protected $dependency = 'composer';

    public function getVersion()
    {
        $response = $this->getConnection()
                         ->exec('yarn --version');

        $versionStart = strpos($response, 'Composer version ') + strlen('Composer version ');
        $versionEnd = strpos($response, ' ', $versionStart);
        $versionLength = $versionEnd - $versionStart;

        return substr($response, $versionStart, $versionLength);
    }

    public function getStatus()
    {
        $response = $this->getConnection()
                         ->exec('composer diagnose', $error);

        $outdated = strpos($response, 'You are not running the latest stable version');

        return $outdated
            ? 'outdated'
            : 'ok';
    }

}