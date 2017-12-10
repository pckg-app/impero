<?php namespace Impero\Dependencies\Record;

use Exception;
use Impero\Dependencies\Dependency\Bower;
use Impero\Dependencies\Dependency\Composer;
use Impero\Dependencies\Dependency\Git;
use Impero\Dependencies\Dependency\Npm;
use Impero\Dependencies\Dependency\Yarn;
use Impero\Dependencies\Entity\Dependencies;
use Impero\Services\Service\SshConnection;
use Pckg\Concept\Reflect;
use Pckg\Database\Record;

class Dependency extends Record
{

    protected $entity = Dependencies::class;

    protected $toArray = ['pivot'];

    protected $handlers = [
        'composer' => Composer::class,
        'npm'      => Npm::class,
        'git'      => Git::class,
        'bower'    => Bower::class,
        'yarn'     => Yarn::class,
    ];

    public function getHandler(SshConnection $connection)
    {
        $handlerClass = $this->handlers[$this->dependency] ?? null;

        if (!$handlerClass) {
            throw new Exception('Handler for dependency ' . $this->name . ' not found!');
        }

        return Reflect::create($handlerClass, [$connection]);
    }

}