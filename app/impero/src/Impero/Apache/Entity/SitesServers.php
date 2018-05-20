<?php namespace Impero\Apache\Entity;

use Impero\Apache\Record\SitesServer;
use Impero\Servers\Entity\Servers;
use Pckg\Database\Entity;

class SitesServers extends Entity
{

    protected $record = SitesServer::class;

    public function site()
    {
        return $this->belongsTo(Sites::class)
                    ->foreignKey('site_id');
    }

    public function server()
    {
        return $this->belongsTo(Servers::class)
                    ->foreignKey('server_id');
    }

}