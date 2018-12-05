<?php namespace Impero\Servers\Migration;

use Pckg\Migration\Migration;

class CreateServersTables extends Migration
{

    public function up()
    {
        /**
         * Servers.
         */
        $servers = $this->table('servers');
        $servers->integer('system_id')->references('systems');
        $servers->varchar('status')->references('list_items', 'slug');
        $servers->varchar('name');
        $servers->varchar('ip');
        $servers->varchar('ptr');
        $servers->varchar('port', 5);
        $servers->varchar('user');

        $tasks = $this->table('tasks');
        $tasks->title();
        $tasks->parent();
        $tasks->varchar('status');
        $tasks->datetime('started_at');
        $tasks->datetime('ended_at');

        $serverCommands = $this->table('server_commands');
        $serverCommands->integer('server_id')->references('servers');
        $serverCommands->integer('task_id')->references('tasks')->nullable();
        $serverCommands->text('command');
        $serverCommands->longtext('info');
        $serverCommands->text('error');
        $serverCommands->varchar('code', 16);
        $serverCommands->datetime('executed_at');

        /**
         * Morphs.
         */
        $serversMorphs = $this->morphtable('servers', 'server_id');
        $serversMorphs->varchar('type');

        /**
         * Services
         */
        $serversServices = $this->table('servers_services');
        $serversServices->integer('server_id')->references('servers');
        $serversServices->integer('service_id')->references('services');
        $serversServices->varchar('status')->references('list_items', 'slug');
        $serversServices->varchar('version');

        $services = $this->table('services');
        $services->varchar('name');
        $services->varchar('service');

        /**
         * Dependencies
         */
        $dependencies = $this->table('dependencies');
        $dependencies->varchar('name');
        $dependencies->varchar('dependency');

        $serversDependencies = $this->table('servers_dependencies');
        $serversDependencies->integer('server_id')->references('servers');
        $serversDependencies->integer('dependency_id')->references('dependencies');
        $serversDependencies->varchar('status_id')->references('list_items', 'slug');
        $serversDependencies->varchar('version');

        /**
         * Websites - sites?
         */
        /**
         * Jobs
         */
        $jobs = $this->table('jobs');
        $jobs->integer('server_id')->references('servers');
        $jobs->varchar('name');
        $jobs->text('command');
        $jobs->varchar('frequency');
        $jobs->varchar('status')->references('list_items', 'slug');

        /**
         * Firewalls
         */
        $firewalls = $this->table('firewalls');
        $firewalls->integer('server_id')->references('servers');
        $firewalls->varchar('rule')->references('list_items', 'slug');
        $firewalls->varchar('from');
        $firewalls->varchar('port');
        $firewalls->varchar('direction')->references('list_items', 'slug');

        /**
         * Logs
         */
        $serverLogs = $this->table('server_logs');
        $serverLogs->integer('server_id')->references('servers');
        $serverLogs->datetime('created_at');
        $serverLogs->varchar('type'); // morph - service, server, deployment, application, ...
        $serverLogs->integer('poly_id');

        /**
         * Operating systems.
         */
        $systems = $this->table('systems');
        $systems->slug();
        $systems->varchar('name');

        /**
         * Tags
         */
        $tags = $this->table('tags');
        $tags->integer('server_id')->references('servers');
        $tags->varchar('tag');

        /**
         * Notifications
         */
        $notifications = $this->table('notifications');
        $notifications->integer('user_id');
        $notifications->datetime('created_at');
        $notifications->text('content');

        /**
         * Api requests
         */

        $apiRequests = $this->table('api_requests');
        $apiRequests->datetime('created_at');
        $apiRequests->longtext('data');
        $apiRequests->varchar('ip');
        $apiRequests->varchar('url');

        /**
         * Secrets.
         */
        $secrets = $this->table('secrets');
        $secrets->varchar('file');
        $secrets->text('keys');

        $this->save();
    }

}