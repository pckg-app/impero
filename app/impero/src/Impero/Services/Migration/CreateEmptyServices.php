<?php namespace Impero\Services\Migration;

use Impero\Apache\Entity\Sites;
use Impero\Apache\Record\Site;
use Impero\Apache\Record\SitesServer;
use Pckg\Migration\Migration;

class CreateEmptyServices extends Migration
{

    public function up()
    {
        /**
         * Migrate database, cronjob and web service by default for all sites.
         */
        $sites = (new Sites())->all();
        $services = collect(['web', 'database', 'cron']);
        $sites->each(
            function(Site $site) use ($services) {
                $services->each(
                    function($service) use ($site) {
                        SitesServer::getOrCreate(
                            ['server_id' => $site->server_id, 'site_id' => $site->id, 'type' => $service]
                        );
                    }
                );
            }
        );
    }

}