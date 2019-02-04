<?php namespace Impero\Apache\Record;

use Impero\Apache\Entity\SitesServers;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
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
        $task = Task::create('Un-deploying ' . $this->type . ' for site #' . $this->site_id . ' on server ' .
                             $this->server_id);

        return $task->make(function() {
            if ($this->type == 'cron') {
                $this->server->removeCronjob($this->site->getHtdocsPath());
            }
        });
    }

    public function redeploy()
    {
        $task = Task::create('Redeploying ' . $this->type . ' for site #' . $this->site_id . ' on server ' .
                             $this->server_id);

        return $task->make(function() {
            $this->undeploy();
            $this->deploy();
        });
    }

    public function deploy()
    {
        $task = Task::create('Deploying ' . $this->type . ' for site #' . $this->site_id . ' on server ' .
                             $this->server_id);

        return $task->make(function() {
            if ($this->type == 'cron') {
                $this->site->deployCronService($this->server);
            } elseif ($this->type == 'web') {
                $this->site->deployConfigService($this->server);
            }
        });
    }

}