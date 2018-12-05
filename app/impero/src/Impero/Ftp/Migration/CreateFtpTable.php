<?php namespace Impero\Ftp\Migration;

use Pckg\Migration\Migration;

class CreateFtpTable extends Migration
{

    public function up()
    {
        $ftpTable = $this->table('ftps');

        $ftpTable->varchar('username', 128);
        $ftpTable->varchar('password', 255);
        $ftpTable->varchar('path', 255);
        $ftpTable->integer('user_id')->references('users');

        $this->save();
    }

}