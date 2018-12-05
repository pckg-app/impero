<?php namespace Impero\User\Resolver;

use Impero\User\Entity\Users;
use Pckg\Framework\Provider\Helper\EntityResolver;
use Pckg\Framework\Provider\RouteResolver;

class User implements RouteResolver
{

    use EntityResolver;

    protected $entity = Users::class;

}