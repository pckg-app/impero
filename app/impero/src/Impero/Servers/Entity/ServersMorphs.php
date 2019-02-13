<?php namespace Impero\Servers\Entity;

use Impero\Mysql\Entity\Databases;
use Impero\Servers\Record\ServersMorph;
use Pckg\Database\Entity;

class ServersMorphs extends Entity
{

    protected $record = ServersMorph::class;

    public function database()
    {
        return $this->belongsTo(Databases::class)->foreignKey('poly_id')->where('morph_id', Databases::class);
    }

    public function server()
    {
        return $this->belongsTo(Servers::class)->foreignKey('server_id');
    }

}