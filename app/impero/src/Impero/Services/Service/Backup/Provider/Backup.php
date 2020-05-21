<?php namespace Impero\Services\Service\Backup\Provider;

use Impero\Sites\Console\MoveSite;
use Impero\Services\Service\Docker\Console\ActivateDocker;
use Impero\Services\Service\Backup\Console\CreateSiteBackup;
use Impero\Services\Service\Backup\Console\RestoreSiteBackup;
use Pckg\Framework\Provider;

class Backup extends Provider
{

    public function consoles()
    {
        return [
            CreateSiteBackup::class,
            RestoreSiteBackup::class,
            ActivateDocker::class,
            MoveSite::class,
        ];
    }

}