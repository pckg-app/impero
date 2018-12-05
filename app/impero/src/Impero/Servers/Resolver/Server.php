<?php namespace Impero\Servers\Resolver;

use Impero\Servers\Entity\Servers;
use Pckg\Framework\Provider\Helper\EntityResolver;
use Pckg\Framework\Provider\RouteResolver;

class Server implements RouteResolver
{

    use EntityResolver;

    protected $entity = Servers::class;

}