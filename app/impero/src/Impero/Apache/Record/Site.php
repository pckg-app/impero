<?php namespace Impero\Apache\Record;

use Impero\Apache\Console\DumpVirtualhosts;
use Impero\Apache\Entity\Sites;
use Impero\Apache\Entity\SitesServers;
use Impero\Mysql\Entity\Databases;
use Impero\Mysql\Entity\Users;
use Impero\Mysql\Record\Database;
use Impero\Mysql\Record\User as DatabaseUser;
use Impero\Servers\Entity\ServersMorphs;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\ServersMorph;
use Impero\Servers\Record\Task;
use Impero\Services\Service\Apache\DeployApacheService;
use Impero\Services\Service\Config\DeployConfigService;
use Impero\Services\Service\Cron\DeployCronService;
use Impero\Services\Service\Storage\DeployStorageResource;
use Impero\Services\Service\Checkout\ExecuteCheckoutProcedure;
use Impero\Services\Service\Checkout\ExecutePrepareProcedure;
use Impero\Services\Service\Connection\SshConnection;
use Impero\Services\Service\Mysql\DeployMysqlService;
use Impero\Services\Service\Rsync;
use Pckg\Database\Record;
use Pckg\Framework\Console\Command;
use Pckg\Generic\Record\SettingsMorph;
use Pckg\Queue\Service\Cron\Fork;
use Throwable;
use Pckg\Queue\Service\RabbitMQ;

class Site extends Record
{

    protected $entity = Sites::class;

    protected $vars = [];

    /**
     * Build edit url.
     *
     * @return string
     */
    public function getEditUrl()
    {
        return url('apache.edit', ['site' => $this]);
    }

    /**
     * Build delete url.
     *
     * @return string
     */
    public function getDeleteUrl()
    {
        return url('apache.delete', ['site' => $this]);
    }

    public function setUserIdByAuthIfNotSet()
    {
        if (!$this->user_id) {
            $this->user_id = auth()->user('id');
        }

        return $this;
    }

    public function getVirtualhost(Server $server)
    {
        return $this->getInsecureVirtualhost($server) . "\n\n" . $this->getSecureVirtualhost($server);
    }

    public function getInsecureVirtualhost(Server $server)
    {
        $directives = $this->getBasicDirectives();

        $port = $server->getSettingValue('service.apache2.httpPort', 80);

        $return = '<VirtualHost *:' . $port . '>' . "\n\t" . implode("\n\t", $directives) . "\n" . '</VirtualHost>';

        return $return;
    }

    public function getBasicDirectives()
    {
        $directives = [
            'ServerName ' . $this->server_name,
            'DocumentRoot ' . $this->getHtdocsPath(),
        ];

        if ($this->server_alias) {
            $directives[] = 'ServerAlias ' . $this->server_alias;
        }

        if ($this->error_log) {
            $directives[] = 'ErrorLog ' . $this->getLogPath() . 'error.log';
        }

        if ($this->access_log) {
            $directives[] = 'CustomLog ' . $this->getLogPath() . 'access.log combined';
        }

        return $directives;
    }

    public function getHtdocsPath()
    {
        return $this->getDomainPath() . 'htdocs/';
    }

    public function getDomainPath()
    {
        return $this->getUserPath() . $this->document_root . '/';
    }

    public function getUserPath()
    {
        return $this->getMountpoint() . $this->user->username . '/';
    }

    public function getMountpoint()
    {
        /**
         * volume1 - /www/ /backups/ /logs/
         * volume2 - /www/ /backups/ /logs/
         * zero - /www/ -> /volume1/www/
         * We should change this so on every server storage path is the same. We won't use www folder anymore.
         * Sites will point to /mnt/$volume/www/.
         * Volumes are mounted on one server and shared over nfs on other servers, locations are the same.
         * When there's no volume attached we create /www/ folder and mount /mnt/www/ to it.
         */
        return '/www/';
    }

    public function getLogPath()
    {
        return $this->getDomainPath() . 'logs/';
    }

    public function getSecureVirtualhost(Server $server)
    {
        if (!$this->ssl) {
            return;
        }

        $directives = $this->getBasicDirectives();
        $directives[] = 'SSLEngine on';
        //$directives[] = 'SSLCertificateFile ' . $this->getSslPath() . $this->ssl_certificate_file;
        $directives[] = 'SSLCertificateFile ' . $this->getSslPath() . $this->ssl_certificate_chain_file;
        $directives[] = 'SSLCertificateKeyFile ' . $this->getSslPath() . $this->ssl_certificate_key_file;
        //$directives[] = 'SSLCertificateChainFile ' . $this->getSslPath() . $this->ssl_certificate_chain_file;

        $directives[] = 'Header always set Strict-Transport-Security "max-age=15768000"';

        $port = $server->getSettingValue('service.apache2.httpsPort', 443);

        return '<VirtualHost *:' . $port . '>
    ' . implode("\n\t", $directives) . '
</VirtualHost>';
    }

    public function getSslPath()
    {
        return $this->getDomainPath() . 'ssl/';
    }

    public function getServiceServers($service)
    {
        if (in_array($service, ['web', 'db', 'lb', 'cron'])) {
            /**
             * Service is by default active on main server.
             * Check if it's balanced or put on other server.
             */
            return [$this->server];
        }

        return [];
    }

    public function getVirtualhostNginx()
    {
        /**
         * @T00D00 - nginx servers static files over single domain ($id.cdn.startcomms.com)
         *         - static domain is removed from apache and accessible only in nginx
         *         - additional haproxy backends are created
         *         - it is defined in settings_morphs for each site in impero.domains.static setting
         *         - for now we can remove *.cdn.startcomms.com from apache and add it to nginx
         *         - https certificates are normally acquired
         */
        $allHttpToHttps = 'server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    return 301 https://$host$request_uri;
}';

        /**
         * 80 -> 8080
         * 443 -> 8082
         */
        $appName = 'www' . $this->id;
        $httpPort = 8080;
        $httpsPort = 8082;
        $toPort = 443;

        $vh = '';
        $vh .= 'upstream ' . $appName . ' {' . "\n";

        $loadBalancers = [
            '10.8.0.1',
        ];
        foreach ($loadBalancers as $loadBalancer) {
            $vh .= '    server ' . $loadBalancer . ':' . $toPort . ';' . "\n";
        }
        $vh .= '}' . "\n";

        // http
        /*$vh .= 'server {' . "\n";
        $vh .= '    listen ' . $httpPort . ";\n";
        $vh .= '    listen [::]:' . $httpPort . ";\n";
        $vh .= '    server_name ' . collect([$this->server_name])
                ->pushArray(explode(' ', $this->server_alias))
                ->removeEmpty()
                ->unique()
                ->implode(' ') . ";\n";

        $vh .= '    location / {' . "\n";
        $vh .= '	    proxy_pass http://' . $appName . ';' . "\n";
        $vh .= '	    proxy_set_header X-Real-IP $remote_addr;' . "\n";
        $vh .= '	    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;' . "\n";
        $vh .= '	    proxy_set_header X-Forwarded-Proto $scheme;' . "\n";
        $vh .= '    }' . "\n";
        $vh .= '}' . "\n";*/

        // https
        $vh .= 'server {' . "\n";
        $vh .= '    listen ' . $httpsPort . " default ssl;\n";
        $vh .= '    listen [::]:' . $httpsPort . " default ssl;\n";
        $vh .= '    server_name ' . collect([$this->server_name])
                ->pushArray(explode(' ', $this->server_alias))
                ->removeEmpty()
                ->unique()
                ->implode(' ') . ";\n";

        $vh .= '    ssl on;' . "\n";
        $vh .= '    ssl_certificate ' . $this->getSslPath() . 'fullchain.pem;' . "\n";
        $vh .= '    ssl_certificate_key ' . $this->getSslPath() . 'privkey.pem;' . "\n";

        $vh .= 'location /storage/ {
    alias ' . $this->getHtdocsPath() . 'storage/;
  }' . "\n";

        $vh .= 'location /cache/ {
    alias ' . $this->getHtdocsPath() . 'storage/cache/www/;
  }' . "\n";

        $vh .= '    location / {' . "\n";
        $vh .= '	    proxy_pass https://' . $appName . ';' . "\n";
        $vh .= '	    proxy_set_header X-Real-IP $remote_addr;' . "\n";
        $vh .= '	    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;' . "\n";
        $vh .= '	    proxy_set_header X-Forwarded-Proto $scheme;' . "\n";
        $vh .= '    }' . "\n";
        $vh .= '}' . "\n";

        return $vh;
    }

    public function getFullDocumentRoot()
    {
        return '/www/USER/' . $this->document_root . '/htdocs/';
    }

    public function addCronjob($command)
    {
        $this->server->addCronjob($command);
    }

    public function hasSiteDir($dir)
    {
        $connection = $this->getServerConnection();

        return $connection->dirExists($this->getHtdocsPath() . $dir);
    }

    /**
     * @return SshConnection
     */
    public function getServerConnection()
    {
        return $this->server->getConnection();
    }

    public function hasRootDir($dir)
    {
        $connection = $this->getServerConnection();

        return $connection->dirExists($dir);
    }

    public function hasSiteSymlink($symlink)
    {
        $connection = $this->getServerConnection();

        return $connection->symlinkExists($symlink);
    }

    public function hasSiteFile($file)
    {
        $connection = $this->getServerConnection();

        return $connection->fileExists($this->getHtdocsPath() . $file);
    }

    /**
     * Delete:
     *  - cronjobs (cron)
     *  - site (apache, nginx, haproxy)
     *  - storage (htdocs, logs, ssl)
     *  - databases associated only with site (mysql master, mysql slave, haproxy)
     *  - database users associated only with site (mysql master, mysql slave)
     *  - backups associated only with site (storage, mysql, config)
     *  - inactivate pendo, mailo, condo, ... api keys
     * We want to:
     *  - remove site from cronjobs
     *  - remove site from haproxy
     *  - remove site from apache
     *  - remove site from nginx
     *  - delete /www/$user/$site directory
     *  - delete /storage/$user/$site directory
     *  - remove database and disable sync on slave
     *  - remove database and disable binlog on master
     *  - remove https certificates in /etc/letsencrypt/live/$domain
     */
    public function undeploy(Command $destroySite)
    {
        /**
         * Create full backup of all services, resources and configuration.
         */
        $this->createFullBackup();

        /**
         * Undeploy all cronjobs from all servers.
         */
        $this->removeFromCron();

        /**
         * Remove HTTP and HTTPS access to all servers.
         */
        $this->removeFromWeb();

        /**
         * Remove Letsencrypt certificates and https entrypoint.
         */
        $this->removeLetsencrypt();

        /**
         * Delete all volume directories.
         */
        $this->removeFromStorage();

        /**
         * Disable master and slave replication.
         * Delete databases from all replicas.
         * Remove users from all servers.
         */
        $this->removeFromDatabase();

        /**
         * Delete storage, database and config backups.
         */
        $this->removeFromBackups();

        /**
         * Invalidate all api keys created during checkout (center, pendo, mailo, ...).
         */
        $this->invalidateApiKeys();

        /**
         * Reload all system services:
         *   - apache for dynamic requests
         *   - nginx for static requests
         *   - haproxy for routing
         */
        $this->restartAllServices();
    }

    public function createFullBackup()
    {
        /**
         * Storage
         *  - htdocs
         *  - logs
         * Mysql
         *  - project databases
         * Config
         *  - config/env.php
         *  - .env
         * Certificates
         *  - /etc/letsencrypt/
         * Upload everything to DO spaces.
         */
    }

    public function removeFromCron()
    {
        (new SitesServers())->where('site_id', $this->id)->where('type', 'cron')->all()->each(function(
            SitesServer $sitesServer
        ) {
            $sitesServer->undeploy();
            $sitesServer->delete();
        });
    }

    public function removeFromWeb()
    {
        /**
         * Delete htdocs, logs and ssl directories, as well as parent site directory.
         */
        (new SitesServers())->where('site_id', $this->id)->where('type', 'web')->all()->each(function(
            SitesServer $sitesServer
        ) {
            /**
             * This will also restart apache and delete SitesServer entry.
             */
            $sitesServer->undeploy();
        });
        (new SitesServers())->where('site_id', $this->id)->where('type', 'config')->all()->each(function(
            SitesServer $sitesServer
        ) {
            $sitesServer->undeploy();
            $sitesServer->delete();
        });
    }

    public function removeLetsencrypt()
    {
        /**
         * Delete ssl symlinks.
         * Invalidate certificate? Backup certificate?
         * Delete letsencrypt history.
         */
        $domain = $this->document_root;

        $archiveDir = '/etc/letsencrypt/archive/' . $domain;
        $liveDir = '/etc/letsencrypt/live/' . $domain;
        $renewalConfig = '/etc/letsencrypt/renewal/' . $domain . '.conf';
        $connection = $this->getServerConnection();

        foreach ([$archiveDir, $liveDir] as $dir) {
            if (!$connection->dirExists($dir)) {
                continue;
            }

            $connection->deleteDir($dir, true);
        }
        foreach ([$renewalConfig] as $file) {
            if (!$connection->fileExists($file)) {
                continue;
            }

            $connection->deleteFile($dir, true);
        }
    }

    public function removeFromStorage()
    {
        /**
         * Delete storage volumes.
         */
        $user = $this->user->username;
        $domain = $this->document_root;

        $storageDir = '/mnt/volume-fra1-01/live/' . $user . '/' . $domain;
        $checkoutDir = '/mnt/volume-fra1-02/www/' . $user . '/' . $domain;
        $connection = $this->getServerConnection();

        foreach ([$storageDir, $checkoutDir] as $dir) {
            if (!$connection->dirExists($dir)) {
                continue;
            }

            $connection->deleteDir($dir, true);
        }
    }

    public function removeFromDatabase()
    {
        /**
         * Delete databases
         * Delete users
         */
        $databases = (new Databases())->where('name', $this->getSiteDatabases())->all();
        $databases->each(function(Database $database) {
            /**
             * Delete database users.
             */
            (new Users())->where('name', $database->name)->delete();

            $slave = (new ServersMorphs())->where('type', 'database:slave')
                                          ->where('morph_id', Databases::class)
                                          ->where('poly_id', $database->id)
                                          ->one();
            if ($slave) {
                $this->dereplicateDatabasesFromSlave($slave->server);
                $slave->delete();
                /**
                 * We have to drop database!
                 */
                echo('Slave: DROP DATABASE `' . $database->name . '`' . "\n");
            }

            echo('Master: DROP DATABASE `' . $database->name . '`' . "\n");

            /**
             * Delete database.
             */
            $database->delete();
        });
        (new SitesServers())->where('site_id', $this->id)->delete();
    }

    public function removeFromBackups()
    {
        /**
         * Remove storage, database and config backups
         * Remove backup keys
         * All except last backup
         */
    }

    public function invalidateApiKeys()
    {
        /**
         * @T00D00 - remove this to cleanup or something
         *         - invalidate mailo, pendo, ... api keys
         */
    }

    public function restartAllServices()
    {
        /**
         * Haproxy
         * Apache
         * Mysql
         * Nginx
         */
    }

    public function addCronWorker(Server $server, $pckg, $vars)
    {
        $task = Task::create('Adding cron worker for site #' . $this->id . ' on server #' . $server->id);

        return $task->make(function() use ($server, $pckg, $vars) {

        });
    }

    public function addWebWorker(Server $server, $vars = [])
    {
        $this->vars = $vars;

        $task = Task::create('Adding web worker for site #' . $this->id . ' on server #' . $server->id);

        return $task->make(function() use ($server) {
            /**
             * Link server and site's service.
             */
            SitesServer::getOrCreate(['server_id' => $server->id, 'site_id' => $this->id, 'type' => 'web']);

            /**
             * Create webserver directories.
             */
            $this->createOnFilesystem($server);

            /**
             * Checkout platform.
             * We will checkout platform in same way as on main server.
             */
            $this->executeCheckoutProcedure($server);

            /**
             * We are extending site to another server.
             * Currently we've manually mounted NFS to /mnt/, in future it has to happen automatically.
             *
             * @T00D00 - check that volume is actually mounted
             * @T00D00 - check that tmp directory is local, cache is shared
             */
            $this->deployStorageService($server);

            /**
             * We do not want to copy config, we want to create fresh dump.
             */
            $this->deployConfigService($server);

            /**
             * Database don't need to be changed
             * Platform was already prepared.
             * Cronjobs should be extended manually as service.
             */

            /**
             * SSL - live and archive directories are manually mounted.
             * Path is then the same.
             */

            /**
             * Restart new worker and add it to entrypoint.
             */
            (new DumpVirtualhosts())->executeManually(['--server' => $server->id]);
            (new DumpVirtualhosts())->executeManually(['--server' => $this->server_id]);
        });
    }

    /**
     * @param Server $server
     *
     * @throws \Exception
     */
    public function createOnFilesystem(Server $server)
    {
        $task = Task::create('Creating site #' . $this->id . ' on filesystem on server #' . $server->id);

        return $task->make(function() use ($server) {
            $connection = $server->getConnection();

            $connection->exec('mkdir -p ' . $this->getHtdocsPath());
            $connection->exec('mkdir -p ' . $this->getLogPath());
            $connection->exec('mkdir -p ' . $this->getSslPath());
        });
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function executeCheckoutProcedure(Server $server)
    {
        (new ExecuteCheckoutProcedure())->executeManually([
                                                              '--server' => $server->id,
                                                              '--site'   => $this->id,
                                                          ]);
    }

    public function getImperoPckgAttribute()
    {
        if ($this->hasKey('imperoPckg')) {
            return $this->data('imperoPckg');
        }

        $setting = SettingsMorph::getSettingOrDefault('impero.pckg', Sites::class, $this->id, []);

        $this->set('imperoPckg', $setting);

        return $setting;
    }

    public function getLinkedDir($pckg)
    {
        return '/www/_linked/' . str_replace(['.', '@', '/', ':'], '-', $pckg['repository']) . '/' . $pckg['branch'] .
            '/';
    }

    /**
     * @param Server $server
     * @param        $pckg
     * @param        $aliasDir
     *
     * @throws \Exception
     */
    public function prepareLinkedCheckout(Server $server, $pckg, $aliasDir)
    {
        $connection = $server->getConnection();
        if ($connection->dirExists($aliasDir)) {
            /**
             * Linked checkout is already prepared.
             */
            return;
        }

        /**
         * Create dir, checkout, init and prepare platform.
         */
        $errorStream = null;
        $connection->exec('mkdir -p ' . $aliasDir);

        /**
         * Checkout platform.
         */
        $connection->execMultiple([
                                      'git clone ' . $pckg['repository'] . ' .',
                                      'git checkout ' . $pckg['branch'],
                                  ], $output, $error, $aliasDir);

        /**
         * Init platform.
         */
        $connection->execMultiple($pckg['init'], $output, $error, $aliasDir);
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function deployStorageService(Server $server)
    {
        (new DeployStorageResource())->executeManually([
                                                          '--server' => $server->id,
                                                          '--site'   => $this->id,
                                                      ]);
    }

    public function getStorageDir()
    {
        /**
         * @T00D00 - storage path stays hardcoded for now.
         * We will get it by selecting storage server for platform on platform creation.
         */
        return '/mnt/volume-fra1-01/live/' . $this->user->username . '/' . $this->document_root . '/';
    }

    public function getHtdocsOldPath()
    {
        return $this->getDomainPath() . 'htdocs-old/';
    }

    public function replaceCommands(&$commands, $command)
    {
        if (strpos($command, 'command:') === 0) {
            $pckg = $this->getImperoPckgAttribute();
            $commandSlug = substr($command, strlen('command:'));
            foreach ($pckg['commands'][$commandSlug] ?? [] as $subCommand) {
                $commands[] = $this->replaceVars($subCommand);
            }

            return;
        }

        $commands[] = $this->replaceVars($command);
    }

    public function replaceVars($command, $vars = [])
    {
        /**
         * Default variables are needed for path definition for each service deployments.
         */
        $defaults = [
            '$webDir'     => $this->getHtdocsPath(),
            '$logsDir'    => $this->getLogPath(),
            '$storageDir' => $this->getStorageDir(),
        ];

        /**
         * In most cases $vars is empty.
         * Add $vars to $defaults.
         */
        $replaces = array_merge($defaults, $vars);

        /**
         * Default vars.
         * Add impero vars to $replaces.
         */
        $replaces = array_merge($this->getImperoVarsAttribute(), $replaces);

        $escaped = [];
        foreach ($replaces as $key => $value) {
            $escaped[] = in_array($key, ['$password', '$name']) ? escapeshellarg($value) : $value;
        }

        return str_replace(array_keys($replaces), $escaped, $command);
    }

    public function getImperoVarsAttribute()
    {
        if ($this->hasKey('imperoVars')) {
            return $this->data('imperoVars');
        }

        $setting = SettingsMorph::getSettingOrDefault('impero.vars', Sites::class, $this->id, []);

        $this->set('imperoVars', $setting);

        return $setting;
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function copyConfigFromWorker(Server $server)
    {
        $task = Task::create('Copying config for site #' . $this->id . ' to server #' . $server->id);

        return $task->make(function() use ($server) {
            $pckg = $this->getImperoPckgAttribute();
            $otherWorker = (new ServersMorphs())->where('type', 'web')
                                                ->where('server_id', $server->id, '!=')
                                                ->where('morph_id', Sites::class)
                                                ->where('poly_id', $this->id)
                                                ->one();

            $connection = $server->getConnection();
            foreach ($pckg['checkout']['config'] ?? [] as $dest => $copy) {
                (new Rsync($server->getConnection()))->copyTo($otherWorker, $this->getHtdocsPath() . $dest);
            }
        });
    }

    public function redeploySslService()
    {
        /**
         * Generate certificate only, agree with tos, run in non interactive mode, set rsa key size,
         * set admin email, default webroot path, certificate name, domains, usage with apache
         * and auto expansion.
         */
        $command = 'sudo /opt/letsencrypt/certbot-auto certonly';
        $email = 'letsencrypt.zero.gonparty.eu@schtr4jh.net';
        $webroot = '/var/www/default';
        $domain = $this->server_name;
        $domains = $this->getUniqueDomains();

        $ip = null;
        $realDomains = [];
        $skipped = [];
        foreach ($domains as $d) {
            $i = gethostbyname($d);
            if (!$ip) {
                /**
                 * First ip is real ip?
                 */
                $ip = $i;
            } elseif ($i != $ip) {
                /**
                 * Skip domains on invalid ip.
                 */
                $skipped[] = $d;
                continue;
            }

            $realDomains[] = $d;
        }

        if ($skipped) {
            $this->server->logCommand('Skipping obtaining certificate(s) for domains ' .
                                      collect($skipped)->implode(', ') . ' on ip ' . $ip, null, null, null);
        }

        if ($realDomains) {
            $this->server->logCommand('Obtaining certificate(s) for domains ' . collect($realDomains)->implode(', ') .
                                      ' on ip ' . $ip, null, null, null);
        }

        $realDomains = implode(',', $realDomains);
        $params = '--agree-tos --non-interactive --text --rsa-key-size 4096 --email ' . $email . ' --webroot-path ' .
            $webroot . ' --cert-name ' . $domain . ' --domains "' . $realDomains . '" --webroot --expand';

        /**
         * Execute command.
         */
        $connection = $this->getServerConnection();
        $response = $connection->exec($command . ' ' . $params);

        if (strpos($response, 'Certificate not yet due for renewal')) {
            /**
             * Check if correct certificate is linked?
             */
            return false;
        }

        $congrats = 'Congratulations! Your certificate and chain have been saved at:';
        if (strpos($response, $congrats) === false) {
            /**
             * What happened? Another instance is running?
             */
            return false;
        }

        /**
         * @T00D00 - when certificate is re-issued we need to make backup of it and transfer it to all servers.
         *         - we also need to update path, when needed
         */
        $startCongrats = strpos($response, $congrats);
        $startDir = $startCongrats + strlen($congrats);
        $endDir = strpos($response, '.pem', $startDir);
        $fullPath = trim(substr($response, $startDir, $endDir - $startDir));
        $dir = trim(substr($fullPath, 0, strrpos($fullPath, '/') + 1));

        if (!$dir) {
            return false;
        }

        /**
         * If command is successful update site, dump config and restart apache.
         */
        // $dir = '/etc/letsencrypt/live/' . $domain . '/';

        /**
         * Create symlinks.
         */
        $files = ['cert.pem', 'privkey.pem', 'fullchain.pem'];
        $sslPath = $this->getSslPath();
        foreach ($files as $file) {
            if ($connection->symlinkExists($sslPath . $file)) {
                $connection->deleteFile($sslPath . $file);
            }

            $connection->exec('sudo ln -s ' . $dir . $file . ' ' . $sslPath . $file);
        }

        /**
         * Update site in impero.
         */
        $this->setAndSave([
                              'ssl'                        => 'letsencrypt',
                              'ssl_certificate_file'       => 'cert.pem',
                              'ssl_certificate_key_file'   => 'privkey.pem',
                              'ssl_certificate_chain_file' => 'fullchain.pem',
                              'ssl_letsencrypt_autorenew'  => true,
                          ]);

        /**
         * Dump virtualhosts and restart apache.
         */
        $this->restartApache();
    }

    public function getUniqueDomains()
    {
        return collect([$this->server_name])->pushArray(explode(' ', $this->server_alias))->unique()->removeEmpty();
    }

    /**
     * @throws \Symfony\Component\Console\Exception\LogicException
     */
    public function restartApache()
    {
        (new DumpVirtualhosts())->executeManually(['--server' => $this->server_id]);
    }

    public function queueCheckout(array $scale = [])
    {
        queue('impero/impero/manage', 'site:checkout', ['site' => $this->id, 'scale' => $scale]);
    }

    /**
     * @param Server $server
     * @param        $pckg
     * @param        $vars
     *
     * @throws \Exception
     */
    public function checkout(Server $server)
    {
        $task = Task::create('Checking out site #' . $this->id . ' on server #' . $server->id);

        return $task->make(function() use ($server) {
            /**
             * Create htdocs, logs and ssl directories on $server.
             * If needed we enable https / letsencrypt (on loadbalancer?).
             * Reload and restart apache on new server.
             */
            (new DeployApacheService())->executeManually([
                                                             '--server' => $server->id,
                                                             '--site'   => $this->id,
                                                         ]);

            /**
             * We checkout and initialize standalone platforms or create directory structure and symlinks for linked ones.
             */
            $this->executeCheckoutProcedure($server);

            /**
             * Project is prepared on linked and standalone platforms.
             * We need to apply storage service and execute 'prepare' commands.
             * Here we create proper directories and symlinks.
             */
            $this->deployStorageService($server);

            /**
             * Create database, privileges, enable backups and replication.
             * Also imports clean database, creates admin user and writes configuration.
             */
            $this->deployDatabaseService($server);

            /**
             * Dump config.
             */
            $this->deployConfigService($server);

            /**
             * We probably need to import few things.
             */
            $this->executePrepareProcedure($server);

            /**
             * Everything is ready, we may enable cronjobs.
             */
            $this->deployCronService($server);
        });
    }

    /**
     * @param     $pckg
     *
     * @return Site
     * @throws \Exception
     */
    public function deployWebService(Server $server)
    {
    }

    public function undeployWebService(Server $server, $dump = true)
    {
        $task = Task::create('Undeploying web service for site #' . $this->id . ' on server #' . $server->id);

        return $task->make(function() use ($server, $dump) {
            /**
             * Link server and site's service.
             */
            $sitesServer = SitesServer::gets(['server_id' => $server->id, 'site_id' => $this->id, 'type' => 'web']);

            if (!$sitesServer) {
                return;
            }

            $sitesServer->delete();

            if (!$dump) {
                return;
            }

            /**
             * First remove from haproxy, apache and nginx?
             */
            (new DumpVirtualhosts())->executeManually(['--server' => $server->id]);
        });
    }

    public function undeployDatabaseService(Server $server)
    {
        $task = Task::create('Undeploying database for site #' . $this->id . ' on server #' . $server->id);

        return $task->make(function() use ($server) {

        });
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function deployDatabaseService(Server $server)
    {
        (new DeployMysqlService())->executeManually([
                                                        '--server' => $server->id,
                                                        '--site'   => $this->id,
                                                    ]);
    }

    public function mergeImperoVarsAttribute($vars)
    {
        return $this->setImperoVarsAttribute(array_merge($this->getImperoVarsAttribute(), $vars));
    }

    public function setImperoVarsAttribute($vars)
    {
        $this->data['imperoVars'] = $vars;
        SettingsMorph::makeItHappen('impero.vars', $vars, Sites::class, $this->id, 'array');

        return $this;
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function deployConfigService(Server $server)
    {
        (new DeployConfigService())->executeManually([
                                                         '--server' => $server->id,
                                                         '--site'   => $this->id,
                                                     ]);
    }

    /**
     * @param $file
     *
     * @return string
     * @throws \Exception
     */
    public function getConfigFileContent(SshConnection $connection, $file)
    {
        /**
         * Now, vars may be different on different servers.
         * Before we start with named hosts (such as db.$id.impero) we need to use IPs.
         * IP is determined by location of service, project and network topology.
         * First we will scale web service, so we need to modify $dbDefaultHost variable for config.
         * $dbDefaultHost variable is composed from config (we can use $web.., $cron.., and others in similar way).
         *  - when web and db services are on same host network is local - localhost or 127.0.0.1
         *  - when they are on different host:
         *    - private network ip is used when private network is enabled (10....)
         *    - public network ip is used when private network is not available (159....)
         * /impero knows which services should be communicating from project network settings.
         */
        return $this->replaceVars($connection->sftpRead($this->getHtdocsPath() . $file));
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function executePrepareProcedure(Server $server)
    {
        (new ExecutePrepareProcedure())->executeManually([
                                                             '--server' => $server->id,
                                                             '--site'   => $this->id,
                                                         ]);
    }

    /**
     * @param Server $server
     * @param        $pckg
     */
    public function deployCronService(Server $server)
    {
        (new DeployCronService())->executeManually([
            '--server' => $server->id,
            '--site' => $this->id,
                                                       ]);
    }

    public function check($pckg, $fix = false)
    {
        /**
         * Checks:
         *  - htdocs path exists
         */
        $htdocsDir = $this->getHtdocsPath();
        $storageDir = $this->getStorageDir();
        $checks = [];
        $connection = $this->getServerConnection();

        $checkDirs = [$htdocsDir, $storageDir];
        foreach ($checkDirs as $dir) {
            $checks['dirs'][$dir] = $connection->dirExists($dir) ? 'ok:dir' : null;
        }

        foreach ($pckg['checkout']['create']['dir'] ?? [] as $dir) {
            $parsed = $this->replaceVars($htdocsDir . $dir);
            $checks['dirs'][$storageDir . $dir] = $connection->dirExists($parsed) ? 'ok:dir'
                : ($connection->symlinkExists($parsed) ? 'symlink' : $connection->fileExists($parsed) ? 'file' : null);
        }
        foreach ($pckg['checkout']['symlink']['dir'] ?? [] as $dir) {
            $parsed = $this->replaceVars($htdocsDir . $dir);
            $checks['dirs'][$parsed] = $connection->symlinkExists($parsed) ? 'ok:symlink'
                : ($connection->dirExists($parsed) ? 'dir' : $connection->fileExists($parsed) ? 'file' : null);
        }
        foreach ($pckg['checkout']['symlink']['file'] ?? [] as $dir) {
            $parsed = $this->replaceVars($htdocsDir . $dir);
            $checks['dirs'][$parsed] = $connection->fileExists($parsed)
                ? 'ok:file' : ($connection->dirExists($parsed)
                    ? 'dir' : $connection->symlinkExists($parsed) ? 'symlink' : null);
        }
        foreach ($pckg['resources']['storage']['dir'] ?? [] as $dir) {
            $parsed = $this->replaceVars($storageDir . $dir);
            $checks['dirs'][$parsed] = $connection->dirExists($parsed)
                ? 'ok:dir' : ($connection->symlinkExists($parsed)
                    ? 'symlink' : $connection->fileExists($parsed) ? 'file' : null);
        }
        foreach ($pckg['services']['web']['mount'] ?? [] as $link => $dir) {
            $checks['dirs'][$storageDir . $link] = $connection->symlinkExists($htdocsDir . $link)
                ? 'ok:symlink' : ($connection->dirExists($htdocsDir . $link)
                    ? 'dir' : $connection->fileExists($htdocsDir . $link)
                        ? 'file' : null);
        }

        return $checks;
    }

    /**
     * @param Server $server
     * @param        $pckg
     * @param        $vars
     *
     * @throws \Exception
     */
    public function recheckout(Server $server)
    {
        $task = Task::create('Re-checking out site #' . $this->id . ' on server #' . $server->id);

        return $task->make(function() use ($server) {
            /**
             * Make sure that htdocs, logs and ssl directories are created.
             * Also check that https is active.
             * Shouldn't this already be done on checkout?
             */
            // $this->deployWebService($server);

            /**
             * Move old htdocs path and create a new one.
             */
            $this->createNewHtdocsPath($server);

            /**
             * Make sure that git is checked out, files and directories are linked.
             */
            $this->executeCheckoutProcedure($server);

            $this->deployStorageService($server);

            /**
             * This is not needed since we're able to re-dump config.
             */
            // $this->copyOldConfig($server);
            $this->deployConfigService($server);

            /**
             * Everything is ready, we may enable cronjobs.
             */
            $this->redeployCronService();

            $this->restartApache();
        });
    }

    public function createNewHtdocsPath(Server $server)
    {
        $task = Task::create('Creating new htdocs path');

        return $task->make(function() use ($server) {
            $connection = $server->getConnection();

            /**
             * Move existent htdocs-old to htdocs-old-$datetime
             */
            if ($connection->dirExists($this->getHtdocsOldPath())) {
                $connection->exec('mv ' . $this->getHtdocsOldPath() . ' ' . $this->getHtdocsOlderPath());
            }

            /**
             * Move existent htdocs to htdocs-old
             */
            $connection->exec('mv ' . $this->getHtdocsPath() . ' ' . $this->getHtdocsOldPath());

            /**
             * Create new htdocs path
             */
            $connection->exec('mkdir ' . $this->getHtdocsPath());
        });
    }

    public function getHtdocsOlderPath()
    {
        return $this->getDomainPath() . 'htdocs-old-' . date('YmdHis') . '/';
    }

    /**
     * @param Server $server
     *
     * @return mixed
     */
    public function redeployCronService()
    {
        $task = Task::create('Re-deploying cron service for site #' . $this->id);

        return $task->make(function() {
            (new SitesServers())->where('site_id', $this->id)->where('type', 'cron')->allAndEach(function(
                SitesServer $sitesServer
            ) {
                $sitesServer->redeploy();
            });
        });
    }

    /**
     * @param Server $server
     * @param        $pckg
     * @param        $vars
     * @param bool   $isAlias
     * @param bool   $checkAlias
     *
     * @throws \Exception
     */
    public function deploy(Server $server, $isAlias = false, $checkAlias = false, $migrate = true)
    {
        $task = Task::create('Deploying site #' . $this->id . ' to server #' . $server->id);

        return $task->make(function() use ($server, $isAlias, $checkAlias, $migrate) {
            $pckg = $this->getImperoPckgAttribute();
            $connection = null;
            $htdocsDir = $this->getHtdocsPath();
            $blueGreen = ($pckg['checkout']['type'] ?? null) == 'ab';

            $errorStream = null;
            $deployDir = null;
            if ($blueGreen) {
                /**
                 * We should also support blue-green (or a-b deployments).
                 * This would mean that we checkout code in new directory (/www/_ab/[repository]/[branch]/[a|b|gitCommit].
                 * And then change symlink of /www/_abc/[repository]/[branch] to /www/_ab/[repository]/[branch]/[a|b|gitCommit].
                 * ln -sfn /www/_ab/[repository]/[branch]/[a|b|gitCommit] /www/_abc/[repository]/[branch]
                 * Sites are still linked to /www/_abc/[repository]/[branch] and gets updated immediately after symlink change.
                 */
                $deployDir = $this->getBlueGreenDir($pckg);
            } elseif ($checkAlias) {
                /**
                 * Aliased platforms are checkout in _linked directory.
                 */
                $deployDir = $this->getLinkedDir($pckg);
            } elseif (!$isAlias) {
                /**
                 * Standalone platforms are checkout in site's htdocs dir.
                 */
                $deployDir = $htdocsDir;
            }

            if ($deployDir) {
                $connection = $connection ?? $server->getConnection();
                foreach ($pckg['deploy'] ?? [] as $command) {
                    $finalCommands = [];
                    $dirCommand = $deployDir ? 'cd ' . $deployDir . ' && ' : '';
                    $this->replaceCommands($finalCommands, $dirCommand . $command);
                    $connection->execMultiple($finalCommands);
                }
            }

            /**
             * Standalone and aliased platforms are migrated in their htdocs directory.
             */
            if (!$migrate || !($pckg['migrate'] ?? [])) {
                return;
            }

            $connection = $connection ?? $server->getConnection();
            $task = Task::create('Migrating site #' . $this->id);
            $task->make(function() use ($pckg, $deployDir, $connection) {
                $finalCommands = [];
                foreach ($pckg['migrate'] ?? [] as $command) {
                    $dirCommand = $deployDir ? 'cd ' . $deployDir . ' && ' : '';
                    $this->replaceCommands($finalCommands, $dirCommand . $command);
                }
                $connection->execMultiple($finalCommands);
            });
        });
    }

    public function getBlueGreenDir($pckg)
    {
        return '/www/_ab/' . str_replace(['.', '@', '/', ':'], '-', $pckg['repository']) . '/' . $pckg['branch'] .
            '/a|b|gitCommit' . '/';
    }

    /**
     * @param Server $server
     *
     * @throws \Exception
     */
    public function copyOldConfig(Server $server)
    {
        $task = Task::create('Copying old config');

        return $task->make(function() use ($server) {
            $server->getConnection()->exec('cp ' . $this->getHtdocsOldPath() . 'config/env.php ' .
                                           $this->getHtdocsPath() . 'config/env.php');
        });
    }

    /**
     * Undeploy cron service from all servers.
     *
     * @return mixed
     */
    public function undeployCronService()
    {
        $task = Task::create('Un-deploying cron service for site #' . $this->id);

        return $task->make(function() {
            $sitesServers = (new SitesServers())->where('site_id', $this->id)->where('type', 'cron')->all();

            $sitesServers->each->undeploy();
        });
    }

    public function undeployConfigService(Server $server)
    {
        $task = Task::create('Un-deploying config service for site #' . $this->id . ' on server ' . $server->id);

        return $task->make(function() use ($server) {
            $connection = $server->getConnection();
            $pckg = $this->getImperoPckgAttribute();

            foreach ($pckg['checkout']['config'] ?? [] as $dest => $copy) {
                /**
                 * This is kinda not okay until we have a backup of config history.
                 */
                // $connection->deleteFile($this->getHtdocsPath() . $dest);
            }
        });
    }

    /**
     * @return Task
     */
    public function redeployConfigService($immediately = true)
    {
        $task = Task::create('Redeploy config service for site #' . $this->id);

        $action = function() {
            $sitesServers = (new SitesServers())->where('site_id', $this->id)->where('type', 'config')->all();

            if ($sitesServers->count() == 0) {
                /**
                 * Deploy to all web workers.
                 */
                (new SitesServers())->where('site_id', $this->id)->where('type', 'web')->all()->each(function(
                    SitesServer $sitesServer
                ) {
                    $this->deployConfigService($sitesServer->server);
                });
            } else {
                $sitesServers->each(function(SitesServer $sitesServer) {
                    $sitesServer->redeploy();
                });
            }
        };
        $exception = function(Task $task, Throwable $e) {
            /**
             * Exception was thrown, task is already marked as error.
             * Can we log exception to rollbar?
             * Can we notify admin about exception?
             */
            throw $e;
        };

        if ($immediately) {
            return $task->make($action, $exception);
        }

        return $task->prepare($action);
    }

    public function createFile($file, $content)
    {
        $this->getServerConnection()->sftpSend($content, $this->getHtdocsPath() . $file, null, false);
    }

    public function hasServiceOnServer(Server $server, $service)
    {
        if ($service == 'apache') {
            return true;
        }
    }

    public function getHashAttribute()
    {
        return sha1($this->id . Site::class);
    }

    public function getInfrastructure()
    {
        return [
            'sitesServers'  => (new SitesServers())->where('site_id', $this->id)->all(),
            'serversMorphs' => (new ServersMorphs())->where('morph_id', Sites::class)
                                                    ->where('poly_id', $this->id)
                                                    ->all(),
        ];
    }

    public function setImperoPckgAttribute($pckg)
    {
        $this->data['imperoPckg'] = $pckg;
        SettingsMorph::makeItHappen('impero.pckg', $pckg, Sites::class, $this->id, 'array');

        return $this;
    }

    public function queueReplicateDatabasesToSlave(Server $server)
    {
        queue('impero/impero/manage', 'site:database:replicate-to-slave', ['site' => $this->id, 'to' => $server->id]);
    }

    public function getSiteDatabases()
    {
        $pckg = $this->getImperoPckgAttribute();
        $variables = $this->getImperoVarsAttribute();

        $databases = [];
        foreach ($pckg['services']['db']['mysql']['database'] ?? [] as $database => $config) {
            if ($config['type'] != 'searchOrCreate') {
                continue;
            }

            $databases[] = str_replace(array_keys($variables), array_values($variables), $config['name']);
        }

        return $databases;
    }

    public function dereplicateDatabasesFromSlave(Server $server)
    {
        $databases = $this->getSiteDatabases();

        if (!$databases) {
            return ['success' => false, 'message' => 'No databases'];
        }

        $databases = (new Databases())->where('name', $databases)->all();
        $site = $this;
        $databases->each(function(Database $database) use ($server, $site) {
            $sitesServer = SitesServer::gets([
                                                 'site_id'   => $site->id,
                                                 'server_id' => $server->id,
                                                 'type'      => 'database:slave',
                                             ]);

            $serversMorph = ServersMorph::gets([
                                                   'morph_id'  => Databases::class,
                                                   'poly_id'   => $database->id,
                                                   'type'      => 'database:slave',
                                                   'server_id' => $server->id,
                                               ]);

            if (!$sitesServer || !$serversMorph) {
                return;
            }

            /**
             * Make backup and enable replication on slave.
             */
            $database->dereplicateFrom($server);

            $sitesServer->delete();
            $serversMorph->delete();
        });
    }

    public function replicateDatabasesToSlave(Server $server)
    {
        /**
         * First, get databases associated with site.
         * They are defined in pckg.yaml.
         * They should also be associated with different sites, which are currently not.
         * We will associate them in databases_morphs table (can be associated with servers, users, sites, ...).
         */
        //$variables = post('vars', []);
        //$pckg = post('pckg', []);
        $pckg = $this->getImperoPckgAttribute();
        $variables = $this->getImperoVarsAttribute();

        /**
         * Now we have list of all databases (id_shop and pckg_derive for example) and we need to check that replication is in place.
         */
        $databases = [];
        foreach ($pckg['services']['db']['mysql']['database'] ?? [] as $database => $config) {
            $databases[] = str_replace(array_keys($variables), array_values($variables), $config['name']);
        }

        if (!$databases) {
            return ['success' => false, 'message' => 'No databases'];
        }

        $databases = (new Databases())->where('name', $databases)->all();
        $site = $this;
        $databases->each(function(Database $database) use ($server, $site) {
            $sitesServer = SitesServer::getOrNew([
                                                     'site_id'   => $site->id,
                                                     'server_id' => $server->id,
                                                     'type'      => 'database:slave',
                                                 ]);

            $serversMorph = ServersMorph::getOrNew([
                                                       'morph_id'  => Databases::class,
                                                       'poly_id'   => $database->id,
                                                       'type'      => 'database:slave',
                                                       'server_id' => $server->id,
                                                   ]);
            if (!$sitesServer->isNew() && !$serversMorph->isNew()) {
                /**
                 * Skip existing?
                 *
                 * @T00D00 ... at some point site may have multiple different databases over different servers
                 *         ... link database with server instead
                 * @T00D00 ... implement connections
                 *         ... serve with server: zero@eth1 - one@eth1
                 *         ... database with site
                 *         ... database with server
                 */
                return;
            }

            /**
             * Check that master is configured as master.
             */
            $database->requireMysqlMasterReplication();

            /**
             * Check that binlog is actually created for database.
             */
            $database->replicateOnMaster();

            /**
             * Link database:slave service to it.
             */
            $sitesServer->save();
            $serversMorph->save();

            /**
             * Make backup and enable replication on slave.
             */
            $database->replicateTo($server);
        });
    }

}
