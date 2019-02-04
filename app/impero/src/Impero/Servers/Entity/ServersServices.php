<?php namespace Impero\Servers\Entity;

use Impero\Servers\Record\ServersService;
use Impero\Services\Entity\Services;
use Pckg\Database\Entity;
use Pckg\Generic\Entity\ListItems;

class ServersServices extends Entity
{

    protected $record = ServersService::class;

    public function server()
    {
        return $this->belongsTo(Servers::class)->foreignKey('server_id');
    }

    public function service()
    {
        return $this->belongsTo(Services::class)->foreignKey('service_id');
    }

    public function status()
    {
        return $this->belongsTo(ListItems::class)->foreignKey('status_id')->primaryKey('slug');
    }

}