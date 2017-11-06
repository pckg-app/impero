<?php namespace Impero\Mysql\Migration;

use Pckg\Migration\Migration;

class CreateDatabaseTable extends Migration
{

    public function up()
    {
        $siteTable = $this->table('databases');

        $siteTable->varchar('name', 128)->required();
        $siteTable->integer('server_id')->required()->references('servers');
        $siteTable->integer('user_id')->references('users');

        $this->save();
    }

}