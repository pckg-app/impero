<?php namespace Impero\Apache\Record;

use Impero\Apache\Entity\SitesServers;
use Impero\Servers\Record\Server;
use Pckg\Database\Record;

/**
 * Class SitesServer
 *
 * @package Impero\Apache\Record
 * @property Site   $site
 * @property Server $server
 */
class SitesServer extends Record
{

    protected $entity = SitesServers::class;

    public function undeploy()
    {
        /**
         * web, database, cron, lb
         */
        if ($this->type == 'cron') {
            $this->site->undeployCronService($this->server);
        }
    }

    public function redeploy()
    {
        if ($this->type == 'cron') {
            $this->site->redeployCronService($this->server);
        } else if ($this->type == 'config') {
            // $this->site->redeployConfigService($this->server);
        }
    }

}