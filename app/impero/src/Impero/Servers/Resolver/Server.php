<?php namespace Impero\Servers\Resolver;

use Impero\Servers\Dataset\Servers;
use Pckg\Framework\Provider\Helper\EntityResolver;
use Pckg\Framework\Provider\RouteResolver;

class Server implements RouteResolver
{

    use EntityResolver;

    protected $entity = \Impero\Servers\Entity\Servers::class;

    public function resolve($value)
    {
        return (new Servers())->getServerForUser($value);
    }

}