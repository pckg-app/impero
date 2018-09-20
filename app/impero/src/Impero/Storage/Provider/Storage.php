<?php namespace Impero\Storage\Provider;

use Impero\Storage\Console\UploadToS3;
use Pckg\Framework\Provider;

class Storage extends Provider
{

    public function consoles()
    {
        return [
            UploadToS3::class,
        ];
    }

}