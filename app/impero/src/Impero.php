<?php

use Impero\Impero\Provider\Impero as ImperoProvider;
use Pckg\Framework\Provider;
use Pckg\Generic\Middleware\EncapsulateResponse;
use Pckg\Manager\Middleware\RegisterCoreAssets;

/**
 * Class Impero
 */
class Impero extends Provider
{

    public function providers()
    {
        return [
            ImperoProvider::class,
        ];
    }

    public function middlewares()
    {
        return [
            RegisterCoreAssets::class,
        ];
    }

    public function afterwares()
    {
        return [
            EncapsulateResponse::class,
        ];
    }

}

function maestro_urls($class, $slug, $record, $resolver, $alterslug = null)
{
    if (!$alterslug) {
        $alterslug = $slug;
    }

    return array_merge_array(
        [
            'controller' => $class,
            '',
        ],
        [
            '/' . $alterslug                               => [
                'name' => $slug . '.list',
                'view' => 'index',
                'tags' => ['auth:in'],
            ],
            '/' . $alterslug . '/add'                      => [
                'name' => $slug . '.add',
                'view' => 'add',
                'tags' => ['auth:in'],
            ],
            '/' . $alterslug . '/edit/[' . $record . ']'   => [
                'name'      => $slug . '.edit',
                'view'      => 'edit',
                'resolvers' => [
                    $record => $resolver,
                ],
                'tags'      => ['auth:in'],
            ],
            '/' . $alterslug . '/delete/[' . $record . ']' => [
                'name'      => $slug . '.delete',
                'view'      => 'delete',
                'resolvers' => [
                    $record => $resolver,
                ],
                'tags'      => ['auth:in'],
            ],
        ]
    );
}