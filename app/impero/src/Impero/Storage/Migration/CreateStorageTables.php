<?php namespace Impero\Storage\Migration;

use Pckg\Migration\Migration;

class CreateStorageTables extends Migration
{

    public function up()
    {
        $storages = $this->table('storages');
        $storages->title();
        $storages->varchar('location');
        
        $this->save();
    }

}