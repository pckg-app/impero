<?php namespace Impero\Ftp\Provider;

use Impero\Ftp\Console\DumpFtpAccounts;
use Impero\Ftp\Controller\Ftp as FtpController;
use Impero\Ftp\Record\Ftp\Resolver as FtpResolver;
use Pckg\Framework\Provider;

class Ftp extends Provider
{

    public function routes()
    {
        return [
            'url' => maestro_urls(FtpController::class, 'ftp', 'ftp', FtpResolver::class, 'ftp/accounts'),
        ];
    }

    public function consoles()
    {
        return [
            DumpFtpAccounts::class,
        ];
    }

}