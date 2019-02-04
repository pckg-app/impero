<?php namespace Impero\Servers\Entity;

use Impero\Apache\Entity\Sites;
use Impero\Dependencies\Entity\Dependencies;
use Impero\Jobs\Entity\Jobs;
use Impero\Mysql\Entity\Databases;
use Impero\Servers\Record\Server;
use Impero\Services\Entity\Services;
use Pckg\Database\Entity;
use Pckg\Generic\Entity\ListItems;
use Pckg\Generic\Entity\Settings;
use Pckg\Generic\Entity\SettingsMorphs;

class Servers extends Entity
{

    protected $record = Server::class;

    public function tags()
    {
        return $this->hasMany(Tags::class)->foreignKey('server_id');
    }

    public function system()
    {
        return $this->belongsTo(Systems::class)->foreignKey('system_id');
    }

    public function status()
    {
        return $this->belongsTo(ListItems::class)
                    ->foreignKey('status')
                    ->primaryKey('slug')
                    ->where('list_items.list_id', 'servers.status');
    }

    public function services()
    {
        return $this->hasAndBelongsTo(Services::class)
                    ->over(ServersServices::class)
                    ->leftForeignKey('server_id')
                    ->rightForeignKey('service_id');
    }

    public function dependencies()
    {
        return $this->hasAndBelongsTo(Dependencies::class)
                    ->over(ServersDependencies::class)
                    ->leftForeignKey('server_id')
                    ->rightForeignKey('dependency_id');
    }

    public function jobs()
    {
        return $this->hasMany(Jobs::class)->foreignKey('server_id');
    }

    public function settings()
    {
        return $this->morphsMany(Settings::class)->over(SettingsMorphs::class)->foreignKey('server_id');
    }

    public function masterDatabases()
    {
        return $this->hasMany(Databases::class)->foreignKey('server_id');
    }

    public function slaveDatabases()
    {
        return $this->morphsMany(Databases::class)->over(DatabasesMorphs::class)->foreignKey('database_id');
    }

    public function sites()
    {
        return $this->hasMany(Sites::class)->foreignKey('server_id');
    }

}