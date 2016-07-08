<?php

use Derive\Orders\Migration\OrdersTags;
use Derive\Platform\Migration\CreatePlatformTables;
use Pckg\Auth\Migration\CreateAuthTables;
use Pckg\Dynamic\Migration\CreateDynamicTables;
use Pckg\Furs\Migration\CreateFursTable;
use Pckg\Generic\Migration\CreateGenericTables;

return [
    CreateAuthTables::class,
    CreateDynamicTables::class,
    CreateGenericTables::class,
    OrdersTags::class,
    //InstallDynamicTables::class,
    CreatePlatformTables::class,
    CreateFursTable::class,
];
