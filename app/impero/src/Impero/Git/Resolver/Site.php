<?php namespace Impero\Git\Resolver;

use Impero\Apache\Entity\Sites;
use Pckg\Framework\Provider\RouteResolver;

class Site implements RouteResolver
{

    public function resolve($value)
    {
        return (new Sites())->where('id', $value)->oneOrFail(function() {
            response()->notFound('Site not found');
        });
    }

    public function parametrize($record)
    {
        return $record->id;
    }

}