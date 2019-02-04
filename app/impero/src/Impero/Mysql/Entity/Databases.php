<?php namespace Impero\Mysql\Entity;

use Impero\Mysql\Record\Database;
use Impero\Servers\Entity\Servers;
use Pckg\Database\Entity;
use Pckg\Maestro\Service\Contract\Entity as MaestroEntity;

class Databases extends Entity implements MaestroEntity
{

    protected $record = Database::class;

    /**
     * Build edit url.
     *
     * @return string
     */
    public function getAddUrl()
    {
        return url('database.add');
    }

    public function server()
    {
        return $this->belongsTo(Servers::class)->foreignKey('server_id');
    }

}