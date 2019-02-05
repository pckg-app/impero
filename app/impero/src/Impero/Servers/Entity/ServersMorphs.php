<?php namespace Impero\Servers\Entity;

use Impero\Mysql\Entity\Databases;
use Pckg\Database\Entity;

class ServersMorphs extends Entity
{

    public function database()
    {
        return $this->belongsTo(Databases::class)->foreignKey('poly_id')->where('morph_id', Databases::class);
    }

}