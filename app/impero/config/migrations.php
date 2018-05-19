<?php

use Impero\Apache\Migration\AlterUserTable;
use Impero\Apache\Migration\CreateSiteTable;
use Impero\Ftp\Migration\CreateFtpTable;
use Impero\Mysql\Migration\CreateDatabaseTable;
use Impero\Mysql\Migration\CreateUserTable;
use Impero\Servers\Migration\CreateServersTables;
use Pckg\Auth\Migration\CreateAuthTables;
use Pckg\Dynamic\Migration\CreateDynamicTables;
use Pckg\Generic\Migration\CreateGenericTables;
use Pckg\Generic\Migration\CreateListTables;

return [
    CreateAuthTables::class,
    CreateSiteTable::class,
    CreateFtpTable::class,
    CreateDatabaseTable::class,
    CreateUserTable::class,
    CreateDynamicTables::class,
    CreateGenericTables::class,
    AlterUserTable::class,
    CreateServersTables::class,
    CreateListTables::class,
];