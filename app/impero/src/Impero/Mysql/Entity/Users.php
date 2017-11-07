<?php namespace Impero\Mysql\Entity;

use Impero\Mysql\Record\User;
use Impero\Servers\Entity\Servers;
use Pckg\Database\Entity;
use Pckg\Maestro\Service\Contract\Entity as MaestroEntity;

class Users extends Entity implements MaestroEntity
{

    protected $record = User::class;

    protected $table = 'database_users';

    /**
     * Build edit url.
     *
     * @return string
     */
    public function getAddUrl()
    {
        return url('user.add');
    }

    public function server()
    {
        return $this->belongsTo(Servers::class)
                    ->foreignKey('server_id');
    }

}