<?php namespace Impero\Services\Resolver;

use Impero\Services\Entity\Services;
use Pckg\Framework\Provider\Helper\EntityResolver;
use Pckg\Framework\Provider\RouteResolver;

class Service implements RouteResolver
{

    use EntityResolver;

    protected $entity = Services::class;

}