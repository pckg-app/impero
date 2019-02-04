<?php namespace Impero\Servers\Entity;

use Impero\Dependencies\Entity\Dependencies;
use Impero\Servers\Record\ServersDependency;
use Pckg\Database\Entity;
use Pckg\Generic\Entity\ListItems;

class ServersDependencies extends Entity
{

    protected $record = ServersDependency::class;

    public function server()
    {
        return $this->belongsTo(Servers::class)->foreignKey('server_id');
    }

    public function dependency()
    {
        return $this->belongsTo(Dependencies::class)->foreignKey('dependency_id');
    }

    public function status()
    {
        return $this->belongsTo(ListItems::class)->foreignKey('status_id')->primaryKey('slug');
    }

}