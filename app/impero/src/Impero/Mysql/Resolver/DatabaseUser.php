<?php namespace Impero\Mysql\Resolver;

use Pckg\Framework\Provider\Helper\EntityResolver;
use Pckg\Framework\Provider\RouteResolver;

class DatabaseUser implements RouteResolver
{

    use EntityResolver;

    protected $entity = '';

}