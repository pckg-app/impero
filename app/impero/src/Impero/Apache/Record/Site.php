<?php namespace Impero\Apache\Record;

use Impero\Apache\Console\DumpVirtualhosts;
use Impero\Apache\Entity\Sites;
use Impero\Apache\Entity\SitesServers;
use Impero\Mysql\Record\Database;
use Impero\Mysql\Record\User as DatabaseUser;
use Impero\Servers\Entity\ServersMorphs;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Impero\Services\Service\Connection\SshConnection;
use Impero\Services\Service\Rsync;
use Pckg\Database\Record;
use Pckg\Generic\Record\SettingsMorph;

class Site extends Record
{

    protected $entity = Sites::class;

    protected $vars = [];

    /**
     * @return SshConnection
     */
    public function getServerConnection()
    {
        return $this->server->getConnection();
    }

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

    /**
     * @param Server $server
     *
     * @throws \Exception
     */
    public function createOnFilesystem(Server $server)
    {
        $task = Task::create('Creating site #' . $this->id . ' on filesystem on server #' . $server->id);

        return $task->make(
            function() use ($server) {
                $connection = $server->getConnection();

                $connection->exec('mkdir -p ' . $this->getHtdocsPath());
                $connection->exec('mkdir -p ' . $this->getLogPath());
                $connection->exec('mkdir -p ' . $this->getSslPath());
            }
        );
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

    public function getUserPath()
    {
        return $this->getMountpoint() . $this->user->username . '/';
    }

    public function getDomainPath()
    {
        return $this->getUserPath() . $this->document_root . '/';
    }

    public function getLogPath()
    {
        return $this->getDomainPath() . 'logs/';
    }

    public function getHtdocsPath()
    {
        return $this->getDomainPath() . 'htdocs/';
    }

    public function getHtdocsOldPath()
    {
        return $this->getDomainPath() . 'htdocs-old/';
    }

    public function getHtdocsOlderPath()
    {
        return $this->getDomainPath() . 'htdocs-old-' . date('YmdHis') . '/';
    }

    public function getSslPath()
    {
        return $this->getDomainPath() . 'ssl/';
    }

    public function getVirtualhost(Server $server)
    {
        return $this->getInsecureVirtualhost($server) . "\n\n" . $this->getSecureVirtualhost($server);
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

    public function getInsecureVirtualhost(Server $server)
    {
        $directives = $this->getBasicDirectives();

        $port = $server->getSettingValue('service.apache2.httpPort', 80);

        $return = '<VirtualHost *:' . $port . '>' . "\n\t" . implode("\n\t", $directives) . "\n" . '</VirtualHost>';

        return $return;
    }

    public function getSecureVirtualhost(Server $server)
    {
        if (!$this->ssl) {
            return;
        }

        $directives = $this->getBasicDirectives();
        $directives[] = 'SSLEngine on';
        $directives[] = 'SSLCertificateFile ' . $this->getSslPath() . $this->ssl_certificate_file;
        $directives[] = 'SSLCertificateKeyFile ' . $this->getSslPath() . $this->ssl_certificate_key_file;
        $directives[] = 'SSLCertificateChainFile ' . $this->getSslPath() . $this->ssl_certificate_chain_file;

        $port = $server->getSettingValue('service.apache2.httpsPort', 443);

        return '<VirtualHost *:' . $port . '>
    ' . implode("\n\t", $directives) . '
</VirtualHost>';
    }

    public function addCronjob($command)
    {
        $this->server->addCronjob($command);
    }

    public function getUniqueDomains()
    {
        return collect([$this->server_name])->pushArray(explode(' ', $this->server_alias))->unique()->removeEmpty();
    }

    public function letsencrypt()
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
                 * First ip is real ip.
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
            $this->server->logCommand(
                'Skipping obtaining certificate(s) for domains ' .
                collect($skipped)->implode(', ') . ' on ip ' . $ip, null, null, null
            );
        }

        if ($realDomains) {
            $this->server->logCommand(
                'Obtaining certificate(s) for domains ' . collect($realDomains)->implode(', ') .
                ' on ip ' . $ip, null, null, null
            );
        }

        $realDomains = implode(',', $realDomains);
        $params = '--agree-tos --non-interactive --text --rsa-key-size 4096 --email ' . $email
            . ' --webroot-path ' . $webroot . ' --cert-name ' . $domain . ' --domains "'
            . $realDomains . '" --webroot --expand';

        /**
         * Execute command.
         */
        $connection = $this->getServerConnection();
        $response = $connection->exec($command . ' ' . $params);

        if (strpos($response, 'Certificate not yet due for renewal')) {
            return false;
        }

        $congrats = 'Congratulations! Your certificate and chain have been saved at:';
        if (strpos($response, $congrats) === false) {
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

            $connection->exec('ln -s ' . $dir . $file . ' ' . $sslPath . $file);
        }

        /**
         * Update site in impero.
         */
        $this->setAndSave(
            [
                'ssl'                        => 'letsencrypt',
                'ssl_certificate_file'       => 'cert.pem',
                'ssl_certificate_key_file'   => 'privkey.pem',
                'ssl_certificate_chain_file' => 'fullchain.pem',
                'ssl_letsencrypt_autorenew'  => true,
            ]
        );

        /**
         * Dump virtualhosts and restart apache.
         */
        $this->restartApache();
    }

    public function restartApache()
    {
        (new DumpVirtualhosts())->executeManually(['--server' => $this->server_id]);
    }

    public function hasSiteDir($dir)
    {
        $connection = $this->getServerConnection();

        return $connection->dirExists($this->getHtdocsPath() . $dir);
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

    public function removeLetsencrypt()
    {
        /**
         * Delete ssl symlinks.
         * Invalidate certificate? Backup certificate?
         * Delete letsencrypt history.
         */
    }

    public function createFullBackup()
    {
        /**
         * Storage
         * Mysql
         * Config
         * Certificate
         * Upload everything to space.
         */
    }

    public function removeFromStorage()
    {
        /**
         * Delete storage volumes.
         */
    }

    public function removeFromDatabase()
    {
        /**
         * Delete databases
         * Delete users
         */
    }

    public function removeFromBackups()
    {
        /**
         * Remove storage, database and config backups
         * Remove backup keys
         * All except last backup
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

    public function invalidateApiKeys()
    {
        /**
         * @T00D00 - remove this to cleanup or something
         *         - invalidate mailo, pendo, ... api keys
         */
    }

    /**
     * Delete:
     *  - cronjobs (cron)
     *  - site (apache, nginx, haproxy)
     *  - databases associated only with site (mysql master, mysql slave, haproxy)
     *  - database users associated only with site (mysql master, mysql slave)
     *  - storage (htdocs, logs, ssl)
     *  - backups associated only with site (storage, mysql, config)
     *  - inactivate pendo, mailo, condo, ... api keys
     */
    public function undeploy($pckg, $vars)
    {
        /**
         * - backup
         */
        $this->createFullBackup();

        /**
         * - system
         *   - remove cronjobs
         *   - remove letsencrypt
         */
        $this->sitesServers->filter('type', 'cron')->each->undeploy();
        $this->removeLetsencrypt();

        /** - storage
         *   - delete git checkout (whole htdocs?)
         *   - delete htdocs path
         *   - delete logs path
         *   - delete ssl path
         *   - delete services.storage.dir dirs
         *   - unmount services.web.mount dirs and files
         */
        $this->sitesServers->filter('type', 'web')->each->undeploy();
        $this->removeFromStorage();

        /** - database
         *   - disable master and slave replication
         *   - remove users
         *   - remove databases
         */
        $this->sitesServers->filter('type', 'database')->each->undeploy();
        $this->removeFromDatabase();

        /** - backups
         *   - remove storage, database and config backups
         */
        $this->removeFromBackups();

        /** - project
         *   - invalidate api keys (center, pendo, mailo, ...)
         */
        $this->invalidateApiKeys();

        /** - services
         *   - apache
         *   - nginx
         *   - haproxy
         */
        $this->restartAllServices();
    }

    public function addCronWorker(Server $server, $pckg, $vars)
    {
        $this->vars = $vars;

        $task = Task::create('Adding cron worker for site #' . $this->id . ' on server #' . $server->id);

        return $task->make(
            function() use ($server, $pckg) {

            }
        );
    }

    public function addWebWorker(Server $server, $pckg, $vars)
    {
        $this->vars = $vars;

        $task = Task::create('Adding web worker for site #' . $this->id . ' on server #' . $server->id);

        return $task->make(
            function() use ($server, $pckg) {
                /**
                 * Create webserver directories.
                 */
                $this->createOnFilesystem($server);

                /**
                 * Checkout platform.
                 */
                $this->executeCheckoutProcedure($server, $pckg);

                /**
                 * We are extending site to another server.
                 * All directories are existent.
                 *
                 * @T00D00 - check that volume is actually mounted
                 * @T00D00 - check that tmp directory is local, cache is shared
                 */
                $this->deployStorageService($server, $pckg);

                /**
                 * Copy config.
                 *
                 * @T00D00 - allow different "configurations"
                 */
                $this->copyConfigFromWorker($server, $pckg);

                /**
                 * Database don't need to be changed
                 * Platform was already prepared.
                 * Cronjobs should be extended manually as service.
                 */

                /**
                 * Check for letsencrypt.
                 * Restart apache and haproxy.
                 */
                if (isset($pckg['services']['web']['https'])) {
                    $this->letsencrypt();
                } else {
                    $this->restartApache();
                }
            }
        );
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

        return $task->make(
            function() use ($server) {
                /**
                 * Create htdocs, logs and ssl directories on $server.
                 * If needed we enable https / letsencrypt (on loadbalancer?).
                 * Reload and restart apache on new server.
                 */
                $this->deployWebService($server);

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
            }
        );
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function executePrepareProcedure(Server $server)
    {
        $task = Task::create('Preparing site #' . $this->id);
        return $task->make(
            function() use ($server) {
                /**
                 * Execute prepare commands.
                 */
                $pckg = $this->getImperoPckgAttribute();
                $commands = [];
                foreach ($pckg['prepare'] ?? [] as $command) {
                    $commands[] = $this->replaceVars($command);
                }
                $connection = $server->getConnection();
                $connection->execMultiple($commands);
            }
        );
    }

    /**
     * @param $file
     *
     * @return string
     * @throws \Exception
     */
    public function getConfigFileContent(SshConnection $connection, $file)
    {
        return $this->replaceVars($connection->sftpRead($this->getHtdocsPath() . $file));
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function copyConfigFromWorker(Server $server, $pckg)
    {
        $task = Task::create('Copying config for site #' . $this->id . ' to server #' . $server->id);

        return $task->make(
            function() use ($server, $pckg) {
                $otherWorker = (new ServersMorphs())->where('type', 'web')
                                                    ->where('server_id', $server->id, '!=')
                                                    ->where('morph_id', Sites::class)
                                                    ->where('poly_id', $this->id)
                                                    ->one();

                $connection = $server->getConnection();
                foreach ($pckg['checkout']['config'] ?? [] as $dest => $copy) {
                    (new Rsync($server->getConnection()))->copyTo($otherWorker, $this->getHtdocsPath() . $dest);
                }
            }
        );
    }

    /**
     * @param     $pckg
     *
     * @return Site
     * @throws \Exception
     */
    public function deployWebService(Server $server)
    {
        $task = Task::create('Deploying web service for site #' . $this->id . ' on server #' . $server->id);

        return $task->make(
            function() use ($server) {
                /**
                 * Link server and site's service.
                 */
                SitesServer::getOrCreate(['server_id' => $server->id, 'site_id' => $this->id, 'type' => 'web']);

                /**
                 * Web service, log service and https service.
                 * Impero is web management service, so we create those by default.
                 * However, server_id is only primary server, services may be expanded to other servers.
                 *
                 * @T00D00 - collect all service servers and initial configuration:
                 *         - is loadbalanced? (default: no)
                 *         - web workers? (default: 1; additional: x)
                 *         - mysql master and slave configuration (default: master only; additional: master-slave)
                 *         - storages (default: root; additional: volume)
                 *         Impero should now which services live on which server and how is network connected.
                 *         We need to know about entrypoint (floating ip, server)
                 */
                $this->createOnFilesystem($server);

                /**
                 * Enable https on website.
                 * This will call letsencrypt and ask for new certificate.
                 * It will also add ssl virtualhost and restart apache.
                 */
                $this->letsencrypt();
            }
        );
    }

    public function createNewHtdocsPath(Server $server)
    {
        $task = Task::create('Creating new htdocs path');

        return $task->make(
            function() use ($server) {
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
            }
        );
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
            $checks['dirs'][$storageDir . $dir] = $connection->dirExists($parsed)
                ? 'ok:dir'
                : ($connection->symlinkExists($parsed)
                    ? 'symlink'
                    : $connection->fileExists($parsed)
                        ? 'file'
                        : null);
        }
        foreach ($pckg['checkout']['symlink']['dir'] ?? [] as $dir) {
            $parsed = $this->replaceVars($htdocsDir . $dir);
            $checks['dirs'][$parsed] = $connection->symlinkExists($parsed)
                ? 'ok:symlink'
                : ($connection->dirExists($parsed)
                    ? 'dir'
                    : $connection->fileExists($parsed)
                        ? 'file'
                        : null);
        }
        foreach ($pckg['checkout']['symlink']['file'] ?? [] as $dir) {
            $parsed = $this->replaceVars($htdocsDir . $dir);
            $checks['dirs'][$parsed] = $connection->fileExists($parsed)
                ? 'ok:file'
                : ($connection->dirExists($parsed)
                    ? 'dir'
                    : $connection->symlinkExists($parsed)
                        ? 'symlink'
                        : null);
        }
        foreach ($pckg['services']['storage']['dir'] ?? [] as $dir) {
            $parsed = $this->replaceVars($storageDir . $dir);
            $checks['dirs'][$parsed] = $connection->dirExists($parsed)
                ? 'ok:dir'
                : ($connection->symlinkExists($parsed)
                    ? 'symlink'
                    : $connection->fileExists($parsed)
                        ? 'file'
                        : null);
        }
        foreach ($pckg['services']['web']['mount'] ?? [] as $link => $dir) {
            $checks['dirs'][$storageDir . $link] = $connection->symlinkExists($htdocsDir . $link)
                ? 'ok:symlink'
                : ($connection->dirExists($htdocsDir . $link)
                    ? 'dir'
                    : $connection->fileExists($htdocsDir . $link)
                        ? 'file'
                        : null);
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
            $this->deployWebService($server);

            $this->createNewHtdocsPath($server);

            $this->executeCheckoutProcedure($server);

            $this->deployStorageService($server);

            $this->copyOldConfig($server);

            /**
             * Everything is ready, we may enable cronjobs.
             */
            $this->redeployCronService($server);

            $this->restartApache();
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
    public function deploy(Server $server, $pckg, $vars, $isAlias = false, $checkAlias = false, $migrate = true)
    {
        $task = Task::create('Deploying site #' . $this->id . ' to server #' . $server->id);
        $this->vars = $vars;

        return $task->make(
            function() use ($server, $pckg, $isAlias, $checkAlias, $migrate) {
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
                        $finalCommand = $deployDir ? 'cd ' . $deployDir . ' && ' : '';
                        $finalCommand .= $this->replaceVars($command);
                        $connection->exec($finalCommand);
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
                $task->make(
                    function() use ($pckg, $deployDir, $connection) {
                        foreach ($pckg['migrate'] ?? [] as $command) {
                            $finalCommand = $deployDir ? 'cd ' . $deployDir . ' && ' : '';
                            $finalCommand .= $this->replaceVars($command);
                            $connection->exec($finalCommand);
                        }
                    }
                );
            }
        );
    }

    /**
     * @param Server $server
     *
     * @throws \Exception
     */
    public function copyOldConfig(Server $server)
    {
        $task = Task::create('Copying old config');

        return $task->make(
            function() use ($server) {
                $server->getConnection()->exec(
                    'cp ' . $this->getHtdocsOldPath() . 'config/env.php ' .
                    $this->getHtdocsPath() . 'config/env.php'
                );
            }
        );
    }

    /**
     * @param Server $server
     * @param        $pckg
     */
    public function deployCronService(Server $server)
    {
        $task = Task::create('Enabling cronjobs for site #' . $this->id);

        return $task->make(
            function() use ($server) {
                /**
                 * Link cron service to site and server.
                 */
                $pckg = $this->getImperoPckgAttribute();
                SitesServer::getOrCreate(['site_id'   => $this->id,
                                                         'server_id' => $server->id,
                                                         'type'      => 'cron',
                                                        ]);

                /**
                 * Add cronjob, we also perform uniqueness check.
                 */
                foreach ($pckg['services']['cron']['commands'] ?? [] as $cron) {
                    $command = $this->replaceVars($cron['command']);
                    $server->addCronjob($command);
                }
            }
        );
    }

    public function undeployCronService(Server $server)
    {
        $task = Task::create('Un-deploying cron service for site #' . $this->id . ' on server ' . $server->id);

        return $task->make(function() use ($server) {
            $sitesServers =  (new SitesServers())->where('site_id', $this->id)
                                ->where('server_id', $server->id)
                                ->where('type', 'cron')
                                ->all();

            $sitesServers->each(function(SitesServer $sitesServer) {
                $sitesServer->server->removeCronjob($this->getHtdocsPath());
            });
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

                //$connection->deleteFile($this->getHtdocsPath() . $dest);
            }
        });
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function deployConfigService(Server $server)
    {
        $task = Task::create('Copying config for site #' . $this->id . ' on server #' . $server->id);

        return $task->make(
            function() use ($server) {
                $connection = $server->getConnection();
                $pckg = $this->getImperoPckgAttribute();

                foreach ($pckg['checkout']['config'] ?? [] as $dest => $copy) {
                    $connection->saveContent($this->getHtdocsPath() . $dest, $this->getConfigFileContent($connection, $copy));
                }
            }
        );
    }

    /**
     * @param Server $server
     *
     * @return mixed
     */
    public function redeployCronService(Server $server)
    {
        $task = Task::create('Re-deploying cron service for site #' . $this->id . ' on server ' . $server->id);

        return $task->make(function() use ($server) {
            $this->undeployCronService($server);
            $this->deployCronService($server);
        });
    }

    /**
     * @param Server $server
     *
     * @return mixed
     */
    public function redeployConfigService(Server $server)
    {
        $task = Task::create('Removing config for site #' . $this->id . ' on server ' . $server->id);

        return $task->make(function() use ($server) {
            $this->undeployConfigService($server);
            $this->deployConfigService($server);
        });
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
        $connection->execMultiple(
            [
                'git clone ' . $pckg['repository'] . ' .',
                'git checkout ' . $pckg['branch'],
            ], $output, $error, $aliasDir
        );

        /**
         * Init platform.
         */
        $connection->execMultiple($pckg['init'], $output, $error, $aliasDir);
    }

    public function getLinkedDir($pckg)
    {
        return '/www/_linked/' . str_replace(['.', '@', '/', ':'], '-', $pckg['repository']) . '/' .
            $pckg['branch'] . '/';
    }

    public function getBlueGreenDir($pckg)
    {
        return '/www/_ab/' . str_replace(['.', '@', '/', ':'], '-', $pckg['repository']) . '/' .
            $pckg['branch'] . '/a|b|giTcommit' . '/';
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function executeCheckoutProcedure(Server $server)
    {
        $task = Task::create('Checking out site #' . $this->id . ' on server #' . $server->id);

        return $task->make(
            function() use ($server) {
                /**
                 * All commands will be executed in platform's htdocs path.
                 */
                $connection = $server->getConnection();
                $commands = [];
                $pckg = $this->getImperoPckgAttribute();

                if ($pckg['checkout']['type'] == 'linked') {
                    /**
                     * We need to make sure that repository and branch are already checked-out on filesystem.
                     */
                    $aliasDir = $this->getLinkedDir($pckg);
                    $this->prepareLinkedCheckout($server, $pckg, $aliasDir);

                    /**
                     * Create some dirs, such as www and cache in which we will probably mount some files.
                     * We won't store any data in those directories.
                     */
                    foreach ($pckg['checkout']['create']['dir'] as $dir) {
                        $commands[] = 'mkdir ' . $this->getHtdocsPath() . $dir;
                    }

                    /**
                     * Create dir and file symlinks for shared stuff.
                     */
                    foreach ($pckg['checkout']['symlink']['dir'] as $dir) {
                        $commands[] = 'ln -s ' . $aliasDir . $dir . ' ' . $this->getHtdocsPath() . $dir;
                    }
                    foreach ($pckg['checkout']['symlink']['file'] as $file) {
                        $commands[] = 'ln -s ' . $aliasDir . $file . ' ' . $this->getHtdocsPath() . $file;
                    }

                    $connection->execMultiple($commands);
                } else { // default type = multiple
                    /**
                     * Standalone platforms clones git repository and checkouts branch
                     */
                    $commands = [
                        'git clone ' . $pckg['repository'] . ' .',
                        'git checkout ' . $pckg['branch'],
                    ];

                    /**
                     * We also execute project defined commands for initialization like dependency install.
                     */
                    foreach ($pckg['init'] as $initCommand) {
                        $commands[] = $initCommand;
                    }

                    $errorStream = null;
                    $outputStream = null;
                    $connection->execMultiple($commands, $outputStream, $errorStream, $this->getHtdocsPath());
                }
            }
        );
    }

    public function getStorageDir()
    {
        /**
         * @T00D00 - storage path stays hardcoded for now.
         * We will get it by selecting storage server for platform on platform creation.
         */
        return '/mnt/volume-fra1-01/live/' . $this->user->username . '/' . $this->document_root . '/';
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function deployStorageService(Server $server)
    {
        $task = Task::create('Preparing site #' . $this->id . ' directories on server #' . $server->id);

        return $task->make(
            function() use ($server) {
                $siteStoragePath = $this->getStorageDir();
                $htdocsOldPath = $this->getHtdocsOldPath();
                $connection = $server->getConnection();
                $pckg = $this->getImperoPckgAttribute();

                /**
                 * Create site dir.
                 */
                $hasSiteDir = $connection->dirExists($siteStoragePath);
                if (!$hasSiteDir) {
                    $connection->makeAndAllow($siteStoragePath);
                }

                foreach ($pckg['services']['storage']['dir'] ?? [] as $storageDir) {
                    $hasStorageDir = $connection->dirExists($siteStoragePath . $storageDir);
                    if ($hasStorageDir) {
                        /**
                         * Storage already exists and will be mounted.
                         */
                        continue;
                    }

                    $hasOldDir = $connection->dirExists($htdocsOldPath . $storageDir);
                    if (!$hasOldDir) {
                        /**
                         * Create $storageDir directory in site's directory on storage server.
                         */
                        $connection->makeAndAllow($siteStoragePath . $storageDir);
                        continue;
                    }
                    /**
                     * Existing dirs are copied to storage server.
                     * Recreation is skipped.
                     */
                    $connection->makeAndAllow($siteStoragePath . $storageDir);

                    /**
                     * Transfer contents.
                     *
                     * @T00D00 - check if this is needed at all times?
                     */
                    $connection->exec(
                        'rsync -a ' . $htdocsOldPath . $storageDir . '/ ' . $siteStoragePath .
                        $storageDir . '/ --stats'
                    );
                }

                /**
                 * Create symlink.
                 * Also, check if dir exists
                 *
                 * @T00D00 - check if this should be moved before previous loop?
                 */
                foreach ($pckg['services']['web']['mount'] ?? [] as $linkPoint => $storageDir) {
                    /**
                     * If mount point was previously created, it will be recreated.
                     * If it was directory and it does
                     */
                    $originPoint = $this->replaceVars($storageDir);
                    $fullLinkPoint = $this->getHtdocsPath() . $linkPoint;
                    $connection->exec('ln -s ' . $originPoint . ' ' . $fullLinkPoint);
                }
            }
        );
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
         */
        $replaces = array_merge($defaults, $vars);

        /**
         * Default vars were set on initial deploy, and changed and added afterwards.
         */
        $replaces = array_merge($replaces, $this->vars);

        $escaped = [];
        foreach ($replaces as $key => $value) {
            $escaped[] = $key != '$password'
                ? $value
                : escapeshellarg($value);
        }

        return str_replace(array_keys($replaces), $escaped, $command);
    }

    public function createFile($file, $content)
    {
        $this->getServerConnection()->sftpSend($content, $this->getHtdocsPath() . $file, null, false);
    }

    /**
     * @param Server $server
     * @param        $pckg
     *
     * @throws \Exception
     */
    public function deployDatabaseService(Server $server)
    {
        $task = Task::create('Preparing database for site #' . $this->id);

        return $task->make(
            function() use ($server) {
                /**
                 * Link database to site.
                 */
                $pckg = $this->getImperoPckgAttribute();
                SitesServer::getOrCreate(['site_id' => $this->id, 'server_id' => $server->id, 'type' => 'database']);

                /**
                 * Some defaults.
                 */
                $dbpass = auth()->createPassword(20);

                /**
                 * Create listed mysql databases.
                 */
                foreach ($pckg['services']['db']['mysql']['database'] as $key => $config) {
                    $database = null;

                    if ($config['type'] == 'searchOrCreate') {
                        $dbname = $this->replaceVars($config['name']);

                        /**
                         * Create mysql database, user and set privileges.
                         */
                        $database = Database::createFromPost(
                            [
                                'name'      => $dbname,
                                'server_id' => $server->id,
                            ]
                        );

                        /**
                         * Manually call backup and replication.
                         */
                        $database->requireScriptBackup(
                        ); // temporarly, until we do not automate backups triggered from impero
                        $database->requireMysqlMasterReplication();

                        /**
                         * For configuration.
                         */
                        $this->vars = array_merge(
                            $this->vars,
                            ['$dbname' => $dbname, '$dbpass' => $dbpass]
                        );
                    } elseif ($config['type'] == 'search') {
                        $database = Database::gets(
                            [
                                'server_id' => $server->id,
                                'name'      => $config['name'],
                            ]
                        );
                    }

                    /**
                     * Check for access.
                     */
                    if (!$database) {
                        continue; // skip?
                    }

                    foreach ($config['user'] ?? [] as $user => $privilege) {
                        $dbuser = $this->replaceVars($user);
                        $vars = $this->getImperoVarsAttribute();
                        if (!isset($vars['$dbuser'])) {
                            $vars['$dbuser'] = $dbuser;
                            $this->setImperoVarsAttribute($vars);
                        }
                        DatabaseUser::createFromPost(
                            [
                                'username'  => $dbuser,
                                'password'  => $dbpass,
                                'server_id' => 2,
                                'database'  => $database->id,
                                'privilege' => $privilege,
                            ]
                        );
                    }
                }
            }
        );
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
            'sitesServers'  => (new SitesServers())->where('site_id', $this->id)
                                                   ->all(),
            'serversMorphs' => (new ServersMorphs())->where('morph_id', Sites::class)
                                                    ->where('poly_id', $this->id)
                                                    ->all(),
        ];
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

    public function getImperoVarsAttribute()
    {
        if ($this->hasKey('imperoVars')) {
            return $this->data('imperoVars');
        }

        $setting = SettingsMorph::getSettingOrDefault('impero.vars', Sites::class, $this->id, []);

        $this->set('imperoVars', $setting);

        return $setting;
    }

    public function setImperoVarsAttribute($vars)
    {
        $this->set('imperoVars', $vars);
        SettingsMorph::makeItHappen('impero.vars', $vars, Sites::class, $this->id, 'array');

        return $this;
    }

    public function setImperoPckgAttribute($pckg)
    {
        $this->set('imperoPckg', $pckg);
        SettingsMorph::makeItHappen('impero.pckg', $pckg, Sites::class, $this->id, 'array');

        return $this;
    }

}
