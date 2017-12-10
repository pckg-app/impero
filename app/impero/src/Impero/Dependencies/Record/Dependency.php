<?php namespace Impero\Dependencies\Record;

use Exception;
use Impero\Dependencies\Dependency\AptTransportHttps;
use Impero\Dependencies\Dependency\Bower;
use Impero\Dependencies\Dependency\Composer;
use Impero\Dependencies\Dependency\Curl;
use Impero\Dependencies\Dependency\Git;
use Impero\Dependencies\Dependency\Less;
use Impero\Dependencies\Dependency\Node;
use Impero\Dependencies\Dependency\Npm;
use Impero\Dependencies\Dependency\Unzip;
use Impero\Dependencies\Dependency\Webpack;
use Impero\Dependencies\Dependency\Wget;
use Impero\Dependencies\Dependency\Xvfb;
use Impero\Dependencies\Dependency\Yarn;
use Impero\Dependencies\Dependency\Zip;
use Impero\Dependencies\Entity\Dependencies;
use Impero\Services\Service\SshConnection;
use Pckg\Concept\Reflect;
use Pckg\Database\Record;

class Dependency extends Record
{

    protected $entity = Dependencies::class;

    protected $toArray = ['pivot'];

    protected $handlers = [
        'composer'            => Composer::class,
        'npm'                 => Npm::class,
        'git'                 => Git::class,
        'bower'               => Bower::class,
        'yarn'                => Yarn::class,
        'apt-transport-https' => AptTransportHttps::class,
        'curl'                => Curl::class,
        'less'                => Less::class,
        'node'                => Node::class,
        'unzip'               => Unzip::class,
        'zip'                 => Zip::class,
        'webpack'             => Webpack::class,
        'wget'                => Wget::class,
        'xvfb'                => Xvfb::class,
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