<?php namespace Impero\Impero\Provider;

use Impero\Apache\Provider\Apache as ApacheProvider;
use Impero\Ftp\Provider\Ftp as FtpProvider;
use Impero\Git\Provider\Git as GitProvider;
use Impero\Impero\Controller\Impero as ImperoController;
use Impero\Impero\Middleware\LogApiRequests;
use Impero\Impero\Middleware\LogApiResponses;
use Impero\Mysql\Provider\Mysql as MysqlProvider;
use Impero\Servers\Provider\Servers;
use Impero\Services\Provider\Services;
use Impero\Sites\Provider\Sites;
use Impero\Storage\Provider\Storage;
use Impero\Task\Provider\Task;
use Impero\User\Provider\Users;
use Pckg\Auth\Provider\Auth as AuthProvider;
use Pckg\Dynamic\Provider\DynamicAssets;
use Pckg\Framework\Provider;
use Pckg\Framework\Provider\Frontend;
use Pckg\Generic\Provider\Generic as GenericProvider;
use Pckg\Maestro\Provider\MaestroAssets;
use Pckg\Manager\Provider\Manager as ManagerProvider;

class Impero extends Provider
{

    public function providers()
    {
        return [
            ManagerProvider::class,
            ApacheProvider::class,
            Sites::class,
            FtpProvider::class,
            MysqlProvider::class,
            GitProvider::class,
            Users::class,
            Services::class,
            //DynamicProvider::class,
            AuthProvider::class,
            GenericProvider::class,
            MaestroAssets::class,
            DynamicAssets::class,
            Provider\Framework::class,
            // new generation
            Servers::class,
            Frontend::class,
            Task::class,
            Storage::class,
        ];
    }

    public function routes()
    {
        return [
            'url' => [
                '/maestro-impero' => [
                    'controller' => ImperoController::class,
                    'view'       => 'index',
                ],
                '/'               => [
                    'controller' => ImperoController::class,
                    'view'       => 'intro',
                ],
            ],
        ];
    }

    public function middlewares()
    {
        return [
            LogApiRequests::class,
        ];
    }

    public function afterwares()
    {
        return [
            LogApiResponses::class,
        ];
    }

    public function assets()
    {
        return [
            'main' => [
                '/app/impero/src/Pckg/Generic/public/app.js',
            ],
        ];
    }

}