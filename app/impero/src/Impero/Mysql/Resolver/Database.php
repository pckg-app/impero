<?php namespace Impero\Mysql\Resolver;

use Impero\Mysql\Entity\Databases;
use Pckg\Framework\Provider\Helper\EntityResolver;
use Pckg\Framework\Provider\RouteResolver;

class Database implements RouteResolver
{

    use EntityResolver;

    protected $entity = Databases::class;

}