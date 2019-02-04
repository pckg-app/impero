<?php namespace Impero\Dependencies\Dependency;

class Wget extends AbstractDependency
{

    protected $dependency = 'wget';

    public function getVersion()
    {
        $response = $this->getConnection()->exec('wget version');

        return $response;
    }

    public function getStatus()
    {
        return 'ok';
    }

}