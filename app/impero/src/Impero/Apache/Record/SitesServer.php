<?php namespace Impero\Apache\Record;

use Impero\Apache\Entity\SitesServers;
use Pckg\Database\Record;

class SitesServer extends Record
{

    protected $entity = SitesServers::class;

    public function undeploy()
    {
        /**
         * web, database, cron, lb
         */
        // $this->delete();
    }

}