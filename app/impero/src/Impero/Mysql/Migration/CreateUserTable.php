<?php namespace Impero\Mysql\Migration;

use Pckg\Migration\Migration;

class CreateUserTable extends Migration
{

    public function up()
    {
        $databaseUsers = $this->table('database_users');

        $databaseUsers->varchar('name', 128)->required();
        $databaseUsers->integer('server_id')->references('servers');
        $databaseUsers->integer('user_id')->references('users'); // is this really necessary?

        $this->save();
    }

}