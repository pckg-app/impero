<?php namespace Impero\Sites\Resolver;

use Impero\Apache\Entity\Sites;
use Pckg\Framework\Provider\Helper\EntityResolver;
use Pckg\Framework\Provider\RouteResolver;

class Site implements RouteResolver
{

    use EntityResolver;

    protected $entity = Sites::class;

}