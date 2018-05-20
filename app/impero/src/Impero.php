<?php

use Impero\Impero\Provider\Impero as ImperoProvider;
use Impero\Services\Service\Backup\Console\MakeMysqlBackup;
use Impero\Services\Service\Storage\Console\MakeConfigBackup;
use Impero\Services\Service\Storage\Console\MakeStorageBackup;
use Impero\Services\Service\System\Console\MakeSystemBackup;
use Pckg\Auth\Middleware\LoginWithApiKeyHeader;
use Pckg\Auth\Middleware\RestrictAccess;
use Pckg\Framework\Provider;
use Pckg\Generic\Middleware\EncapsulateResponse;
use Pckg\Manager\Middleware\RegisterCoreAssets;
use Pckg\Queue\Service\Cron;

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
            LoginWithApiKeyHeader::class,
            RestrictAccess::class,
            RegisterCoreAssets::class,
        ];
    }

    public function afterwares()
    {
        return [
            EncapsulateResponse::class,
        ];
    }

    public function consoles()
    {
        return [
            MakeMysqlBackup::class,
            MakeStorageBackup::class,
            MakeSystemBackup::class,
            MakeConfigBackup::class,
        ];
    }

    public function jobs()
    {
        return [
            Cron::createJob(MakeMysqlBackup::class, 'Make database backups')
                ->at(['6:00', '18:00'])
                ->background(),
            Cron::createJob(MakeStorageBackup::class, 'Make storage backups')
                ->at(['3:00', '15:00'])
                ->background(),
            Cron::createJob(MakeSystemBackup::class, 'Make system services backups')
                ->at(['9:00', '21:00'])
                ->background(),
            Cron::createJob(MakeConfigBackup::class, 'Make config backups')
                ->at(['10:00', '22:00'])
                ->background(),
        ];
    }

    public function assets()
    {
        return [
            'vue' => [
                '/dist/build/js/backend.js',
            ],
        ];
    }

}

/**
 * @param      $class
 * @param      $slug
 * @param      $record
 * @param      $resolver
 * @param null $alterslug
 *
 * @return mixed
 * @T00D00 - promote this to frontend_urls and rest_urls.
 */
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