<?php namespace Impero\Apache\Migration;

use Pckg\Migration\Migration;

class CreateSiteTable extends Migration
{

    public function up()
    {
        $siteTable = $this->table('sites');

        $siteTable->id();
        $siteTable->integer('user_id')->references('users', 'id');

        $siteTable->varchar('server_name', 128)->required();
        $siteTable->text('server_alias')->nullable();
        $siteTable->varchar('document_root', 255)->required();

        $siteTable->varchar('ssl', 16)->nullable();
        $siteTable->varchar('ssl_certificate_file', 128)->nullable();
        $siteTable->varchar('ssl_certificate_key_file', 128)->nullable();
        $siteTable->varchar('ssl_certificate_chain_file', 128)->nullable();
        $siteTable->boolean('ssl_letsencrypt_autorenew');

        $siteTable->boolean('error_log')->setDefault(1);
        $siteTable->boolean('access_log')->setDefault(1);

        $siteTable->index('ssl');
        $siteTable->unique('server_name');

        $siteTable->timeable();

        $siteServers = $this->table('site_servers');
        $siteServers->integer('site_id')->references('sites');
        $siteServers->integer('server_id')->references('servers');
        $siteServers->varchar('type'); // web:dynamic, web:static, loadbalancer, mysql:master, mysql:slave, data

        $this->save();
    }

}