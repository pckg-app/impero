<?php namespace Impero\Servers\Record;

use Impero\Apache\Entity\SitesServers;
use Impero\Apache\Record\SitesServer;
use Impero\Jobs\Record\Job;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Service\ConnectionManager;
use Impero\Services\Service\Backup;
use Impero\Services\Service\Connection\Connectable;
use Impero\Services\Service\Connection\ConnectionInterface;
use Impero\Services\Service\Connection\SshConnection;
use Impero\Services\Service\Mysql;
use Impero\Services\Service\MysqlConnection;
use Impero\Services\Service\OpenSSL;
use Impero\Services\Service\Zip;
use Pckg\Database\Record;
use Pckg\Generic\Entity\SettingsMorphs;

class Server extends Record implements Connectable
{

    protected $entity = Servers::class;

    protected $toArray = ['services', 'dependencies', 'jobs'];

    protected $connection;

    protected $mysqlConnection;

    /**
     * @var Mysql
     */
    protected $mysqlService;

    /**
     * @return mixed|SshConnection
     * @throws \Exception
     */
    public function getConnection() : ConnectionInterface
    {
        if (!$this->connection) {
            $connectionManager = context()->getOrCreate(ConnectionManager::class);
            $this->connection = $connectionManager->createConnection($this);
        }

        return $this->connection;
    }

    /**
     * @return mixed|MysqlConnection
     * @throws \Exception
     */
    public function getMysqlConnection()
    {
        if (!$this->mysqlConnection) {
            $this->mysqlConnection = new MysqlConnection($this->getConnection());
        }

        return $this->mysqlConnection;
    }

    public function readFile($file)
    {
        $connection = $this->server->getConnection();
        return $connection->sftpRead($file);
    }

    /**
     * @param null $command
     *
     * @return bool|null|string
     * @throws \Exception
     */
    public function exec($command)
    {
        return $this->getConnection()->exec($command);
    }

    /**
     * @param null $command
     *
     * @return bool|null|string
     * @throws \Exception
     */
    public function execSql($sql)
    {
        return $this->getMysqlConnection()->execute($sql);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function fetchJobs()
    {
        $connection = $this->getConnection();
        $users = [
            'root',
            'impero',
            'www-data',
            'schtr4jh',
        ];
        $jobs = [];
        foreach ($users as $user) {
            $result = $connection->exec('sudo crontab -l -u ' . $user, $error);
            if (!$result) {
                continue;
            }
            $lines = explode("\n", $result);
            foreach ($lines as $line) {
                $line = trim($line);

                if (!$line) {
                    continue;
                }

                $inactive = false;
                if (strpos($line, '#impero') === false) {
                    $inactive = true;
                } elseif (strpos($line, '#') === 0) {
                    continue;
                }

                if (strpos($line, 'MAILTO') === 0) {
                    continue;
                }

                $command = implode(' ', array_slice(explode(' ', $line), 5));
                $frequency = substr($line, 0, strlen($line) - strlen($command));

                Job::create(
                    [
                        'server_id' => $this->id,
                        'name'      => '',
                        'status'    => $inactive
                            ? 'inactive'
                            : 'active',
                        'command'   => $command,
                        'frequency' => $frequency,
                    ]
                );
            }
        }

        return $jobs;
    }

    public function logCommand($command, $info, $error, $e)
    {
        $task = context()->getOrDefault(Task::class);

        return ServerCommand::create(
            [
                'server_id'   => $this->id,
                'task_id'     => $task->id ?? null,
                'command'     => $command,
                'info'        => $info,
                'error'       => ($e
                        ? 'EXCEPTION: ' . exception($e) . "\n"
                        : null) . $error,
                'executed_at' => date('Y-m-d H:i:s'),
                'code'        => null,
            ]
        );
    }

    public function addCronjob($command)
    {
        /**
         * Get current cronjob configuration.
         */
        $cronjobFile = '/backup/run-cronjobs.sh';
        $connection = $this->getConnection();

        /**
         * Add to file if nonexistent.
         */
        $connection->exec('sudo echo "' . $command . '" >> ' . $cronjobFile);
    }

    public function removeCronjob($path)
    {
        /**
         * Get current cronjob configuration.
         */
        $cronjobFile = '/backup/run-cronjobs.sh';
        $connection = $this->getConnection();
        $currentCronjob = $connection->sftpRead($cronjobFile);
        $cronjobs = explode("\n", $currentCronjob);

        $newCronjobs = [];
        foreach ($cronjobs as $cronjob) {
            if (!$cronjob || strpos($cronjob, $path)) {
                /**
                 * Remove empty or matching cronjobs.
                 */
                continue;
            }
            $newCronjobs[] = $cronjob;
        }

        $connection->saveContent($cronjobFile, implode("\n", $newCronjobs) . "\n");
    }

    public function getName()
    {
        return $this->id;
    }

    public function getPrivateIpAttribute()
    {
        /**
         * Get IP from private network interface.
         */
        $eth1 = $this->getSettingValue('server.network.eth1.ip');

        if ($eth1) {
            return $eth1;
        }

        /**
         * Get IP from public network interface.
         */
        $eth0 = $this->getSettingValue('server.network.eth0.ip');

        if ($eth0) {
            return $eth0;
        }

        return $this->ip;
    }

    public function getSettingValue($slug, $default = null)
    {
        return (new SettingsMorphs())
                ->joinSetting()
                ->where('morph_id', Servers::class)
                ->where('poly_id', $this->id)
                ->where('settings.slug', $slug)
                ->one()->value ?? $default;
    }

    public function getMysqlConfig()
    {
        return [];
        /**
         * @T00D00 ...
         *
         */
        return [
            '/etc/mysql/conf.d/replication.cnf' => '',
        ];
        /**
         * Mysql config is separated into:
         */
        // /backup/dbarray.conf
    }

    public function getApachePortsConfig()
    {
        return '# auto generated by /impero. all changes will be lost.
        
Listen ' . $this->getSettingValue('service.apache2.httpPort', 80) . '

<IfModule ssl_module>
        Listen ' . $this->getSettingValue('service.apache2.httpsPort', 443) . '
</IfModule>

<IfModule mod_gnutls.c>
        Listen ' . $this->getSettingValue('service.apache2.httpsPort', 443) . '
</IfModule>';
    }

    public function getApacheConfig()
    {
        /**
         * First, check that apache is active on server.
         */
        $active = $this->getSettingValue('service.apache2.active');
        if (!$active) {
            return null;
        }

        /**
         * Get all sites for web service on this server.
         */
        $sitesServers = (new SitesServers())->where('server_id', $this->id)
                                            ->where('type', 'web')
                                            ->all();

        $server = $this;
        $sitesServers->each(
            function(SitesServer $sitesServer) use (&$virtualhosts, $server) {
                /**
                 * Apache: apache port
                 * Nginx: nginx port
                 * Haproxy: haproxy port
                 */
                $virtualhosts[] = $sitesServer->site->getVirtualhost($server);
            }
        );

        return implode("\n\n", $virtualhosts);
    }

    public function getNginxConfig() {
        /**
         * First, check that nginx is active on server.
         */
        $active = $this->getSettingValue('service.nginx.active');
        if (!$active) {
            return null;
        }

        /**
         * Get all sites that are routed to this server and proxied to workers.
         */
        $sitesServers = (new SitesServers())->where(
            'site_id', (new SitesServers())->select('sites_servers.site_id')
                                           ->where('server_id', $this->id)
                                           ->where('type', 'web')
        )->where('type', 'web')->all()
                                            ->groupBy('site_id');

        $httpPort = $this->getSettingValue('service.nginx.httpPort', 8083);
        $httpsPort = $this->getSettingValue('service.nginx.httpsPort', 8084);

        $config = '# auto generated by /impero. all changes will be lost.' . "\n";

        /**
         * First, make sure that http requests are redirected to https.
         */
        $config .= 'server {
    listen ' . $httpPort . ' default_server;
    listen [::]:' . $httpPort . ' default_server;
    fastcgi_hide_header Set-Cookie;

    # Redirect all HTTP requests to HTTPS with a 301 Moved Permanently response.
    return 301 https://$host' . ($httpsPort != 443 ? ':' . $httpsPort : '') . '$request_uri;
}
';

        /**
         * Then add server for all instances.
         */
        foreach ($sitesServers as $sitesServersGrouped) {
            $site = collect($sitesServersGrouped)->first()->site;
            /**
             * We will allow serving for all domains, but only cdn domain will be passed through haproxy.
             */
            $domains = $site->getUniqueDomains();

            $config .= 'server {
    listen ' . $httpsPort . ' ssl;
    listen [::]:' . $httpsPort . ' ssl;
    
    # Server domains
    server_name ' . implode(' ', $domains->all()) . ';

    # Document root
    root ' . $site->getHtdocsPath() . ';
    
    ';

            if ($site->ssl && $site->ssl_certificate_file) {
                $config .= '# SSL config
    ssl_certificate     ' . $site->getSslPath() . $site->ssl_certificate_chain_file . ';
    ssl_certificate_key ' . $site->getSslPath() . $site->ssl_certificate_key_file . ';
    
    ';
            }
            $config .= '
    # Cookie-less static domain
    fastcgi_hide_header Set-Cookie;

    # Optimize session
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_session_tickets off;

    # Optimize SSL
    ssl_protocols TLSv1 TLSv1.1 TLSv1.2;
    ssl_ciphers \'ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA:ECDHE-RSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-RSA-AES256-SHA256:DHE-RSA-AES256-SHA:ECDHE-ECDSA-DES-CBC3-SHA:ECDHE-RSA-DES-CBC3-SHA:EDH-RSA-DES-CBC3-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:DES-CBC3-SHA:!DSS\';
    ssl_prefer_server_ciphers on;

    # HSTS (ngx_http_headers_module is required) (15768000 seconds = 6 months)
    add_header Strict-Transport-Security max-age=15768000;

    # OCSP Stapling ---
    # fetch OCSP records from URL in ssl_certificate and cache them
    ssl_stapling on;
    ssl_stapling_verify on;

    # Because nginx will serve only specific file types we disable access.
    location / {
        deny all;
    }
    
    # Healthcheck?
    location /robots.txt {
        return 200 \'User-Agent: *\nDisallow: \';
        add_header Content-Type text/plain;
    }

    # Nginx will serve only files from ./htdocs/storage/ directory.
    # We deny all by default and allow only static files.
    location /storage/ {
        deny all;

        location ~ "\.(jpg|jpeg|gif|png|css|js|ico)$" {
            allow all;
            expires 1M;
            access_log off;
            add_header Cache-Control "public";
        }

        try_files $uri =404;
    }

}

';
        }

        return $config;
    }

    public function getHaproxyConfig()
    {
        /**
         * First, check that apache is active on server.
         */
        $active = $this->getSettingValue('service.haproxy.active');
        if (false && !$active) {
            return null;
        }

        /**
         * Get all sites that are routed to this server and proxied to workers.
         */
        $sitesServers = (new SitesServers())->where(
            'site_id', (new SitesServers())->select('sites_servers.site_id')
                                           ->where('server_id', $this->id)
                                           ->where('type', 'web')
        )->where('type', 'web')
                                            ->all()
                                            ->groupBy('site_id');

        $httpPort = $this->getSettingValue('service.haproxy.httpPort', 8080);
        $httpsPort = $this->getSettingValue('service.haproxy.httpsPort', 8082);

        $config = '# auto generated by /impero. all changes will be lost.
        
        global
        log /dev/log    local0
        log /dev/log    local1 notice
        chroot /var/lib/haproxy
        stats socket /run/haproxy/admin.sock mode 660 level admin
        stats timeout 30s
        user haproxy
        group haproxy
        daemon
        
        # Increase TLS session cache size and lifetime to avoid computing too many symmetric keys
        tune.ssl.cachesize 100000
        tune.ssl.lifetime 600
        
        # Set up a TLS record to match a TCP segment size to improve client side rendering of content
        tune.ssl.maxrecord 1460

        # Default SSL material locations
        ca-base /etc/ssl/certs
        crt-base /etc/ssl/private
        
        # Use Mozilla\'s SSL config generator
        # https://mozilla.github.io/server-side-tls/ssl-config-generator/?hsts=no
        # haproxy 1.6.3
        # openssl 1.1.0g
        
        #ssl-default-bind-ciphers ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256
    #ssl-default-bind-options no-sslv3 no-tlsv10 no-tlsv11 no-tls-tickets
    #ssl-default-server-ciphers ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256
    #ssl-default-server-options no-sslv3 no-tlsv10 no-tlsv11 no-tls-tickets

tune.ssl.default-dh-param       2048
ssl-default-bind-options no-sslv3 no-tls-tickets
ssl-default-bind-ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:AES:CAMELLIA:DES-CBC3-SHA:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!MD5:!PSK:!aECDH:!EDH-DSS-DES-CBC3-SHA:!EDH-RSA-DES-CBC3-SHA:!KRB5-DES-CBC3-SHA

defaults
        log     global
        mode    tcp
        option  tcplog
        option  dontlognull
        option  forwardfor
        timeout connect 5000
        timeout client  50000
        timeout server  50000
        errorfile 400 /etc/haproxy/errors/400.http
        errorfile 403 /etc/haproxy/errors/403.http
        errorfile 408 /etc/haproxy/errors/408.http
        errorfile 500 /etc/haproxy/errors/500.http
        errorfile 502 /etc/haproxy/errors/502.http
        errorfile 503 /etc/haproxy/errors/503.http
        errorfile 504 /etc/haproxy/errors/504.http';

        $config .= "\n\n";

        $config .= 'frontend http2https
    # Http listens on on http port and redirects all requests to https
    bind *:' . $httpPort . '
    mode http
    
    # Change http to https port
    #http-request replace-header Host ^(.*?)(:[0-9]+)?$ \1:443
    
    # Change scheme to https and port to https port
    #http-request redirect location https://%[req.hdr(Host)]%[capture.req.uri]
    
    # New line to test URI to see if its a letsencrypt request
    acl letsencrypt-acl path_beg /.well-known/acme-challenge/
    use_backend letsencrypt if letsencrypt-acl
    
frontend all_https
    # Https listens only on https port and forwards requests to backends
    bind *:' . $httpsPort . '
    # alpn h2,http/1.1
    
    mode tcp
    #option tcplog
    
    # send tcp keep alive?
    #option tcpka
    
    # option forwardfor # only on http mode
    
    # This is needed for proper ssl handshake
    tcp-request inspect-delay 5s
    tcp-request content accept if { req_ssl_hello_type 1 }
    
    # We do not allow downgrading to https
    # http-response set-header Strict-Transport-Security max-age=15768000 # only http mode';

        $split = [];
        foreach ($sitesServers as $sitesServersGrouped) {
            $site = collect($sitesServersGrouped)->first()->site;
            $domains = $site->getUniqueDomains();
            $imploded = implode(' ', $domains->all());
            $cdn = implode(' ', $domains->filter(function($domain){
                return strpos($domain, '.cdn.startcomms.com') !== false;
            })->all());
            $nonCdn = implode(' ', $domains->filter(function($domain){
                return strpos($domain, '.cdn.startcomms.com') === false;
            })->all());
            $split[$site->id] = [
                'all'    => $imploded,
                'cdn'    => $cdn,
                'nonCdn' => $nonCdn,
            ];

            /**
             * Match requests by SNI.
             */
            if ($split[$site->id]['cdn']) {
                $config .= "\n" . '    acl bcknd-dynamic-' . $site->id . ' req.ssl_sni -i '
                    . $nonCdn;
                $config .= "\n" . '    acl bcknd-static-' . $site->id . ' req.ssl_sni -i '
                    . $cdn;
            } else {
                $config .= "\n" . '    acl bcknd-dynamic-' . $site->id . ' req.ssl_sni -i '
                    . $imploded;
            }
        }

        foreach ($sitesServers as $sitesServersGrouped) {
            $site = collect($sitesServersGrouped)->first()->site;
            /**
             * Forward requests to backend.
             */
            if ($split[$site->id]['cdn']) {
                $config .= "\n" . '    use_backend backend-dynamic-' . $site->id . ' if bcknd-dynamic-' . $site->id;
                $config .= "\n" . '    use_backend backend-static-' . $site->id . ' if bcknd-static-' . $site->id;
            } else {
                $config .= "\n" . '    use_backend backend-dynamic-' . $site->id . ' if bcknd-dynamic-' . $site->id;
            }
        }
        /**
         * We need to define fallback backend.
         */
        if ($sitesServers) {
            $config .= "\n" . '    default_backend fallback';
        }

        $allWorkers = [];
        foreach ($sitesServers as $sitesServersGrouped) {
            $site = collect($sitesServersGrouped)->first()->site;
            /**
             * Receive list of all server that site is deployed to.
             */
            $workers = collect($sitesServersGrouped)->map('server');

            $config .= "\n" . 'backend backend-dynamic-' . $site->id;
            $config .= "\n" . '    balance roundrobin';
            $config .= "\n" . '    mode tcp';
            //$config .= "\n" . '    cookie PHPSESSID prefix nocache';
            //$config .= "\n" . '    option forwardfor';

            //$config .= "\n" . 'acl clienthello req_ssl_hello_type 1';
            //$config .= "\n" . 'acl serverhello rep_ssl_hello_type 2';

            //$config .= "\n" . 'tcp-request inspect-delay 5s';
            //$config .= "\n" . 'tcp-request content accept if clienthello';
            //$config .= "\n" . 'tcp-request content accept if tls';

            $config .= "\n" . 'option ssl-hello-chk';

            foreach ($workers as $worker) {
                $allWorkers[$worker->id] = $worker;
                $workerHttpsPort = $worker->getSettingValue('service.apache2.httpsPort', 443);
                $config .= "\n" . '    server ' . $site->server_name . '-' . $worker->name
                    . ' ' . $worker->privateIp . ':' . $workerHttpsPort . ' check weight '
                    . $worker->getSettingValue('service.haproxy.weight', 1);
                // 'cookie ' . $site->server_name . '-' . $worker->name; // ssl verify none
            }

            if (!$split[$site->id]['cdn']) {
                continue;
            }

            $config .= "\n" . 'backend backend-static-' . $site->id;
            $config .= "\n" . '    balance roundrobin';
            $config .= "\n" . '    mode tcp';

            $config .= "\n" . 'option ssl-hello-chk';

            foreach ($workers as $worker) {
                $allWorkers[$worker->id] = $worker;
                $workerHttpsPort = $worker->getSettingValue('service.nginx.httpsPort', 8084);
                $config .= "\n" . '    server ' . $site->server_name . '-' . $worker->name
                    . ' ' . $worker->privateIp . ':' . $workerHttpsPort . ' check weight '
                    . $worker->getSettingValue('service.haproxy.weight', 1);
            }
        }

        $firstWorker = collect($allWorkers)->first();
        if ($firstWorker) {
            $config .= '
        backend fallback
            balance roundrobin
            mode tcp
            option ssl-hello-chk
            server fallback-' . $firstWorker->name . ' ' . $worker->privateIp . ':'
                . $worker->getSettingValue('service.apache2.httpsPort', 443)
                . ' check weight 1
        ';
        }
        $config .= '
        backend letsencrypt
            balance roundrobin
            mode http
            server letsencrypt 127.0.0.1:8080 check weight 1
        ';

        $config .= "\n\n";

        $config .= 'listen stats # Define a listen section called "stats"
  bind :9000 # Listen on localhost:9000
  mode http
  stats enable  # Enable stats page
  stats hide-version  # Hide HAProxy version
  stats realm Haproxy\ Statistics  # Title text for popup window
  stats uri /haproxy_stats  # Stats URI
#  stats auth Username:Password  # Authentication credentials%';

        return $config;
    }

    public function getReplicationConfigLocation()
    {
        return '/etc/mysql/conf.d/replication.cnf';
    }

    /**
     * @param $destination
     * @param $content
     *
     * @throws \Exception
     */
    public function writeFile($destination, $content)
    {
        $local = '/tmp/server.' . $this->id . '.' . sha1($destination);
        file_put_contents($local, $content);
        $this->getConnection()->sftpSend($local, $destination);
        unlink($local);
    }

    /**
     * @param $file
     *
     * @return null|string
     * @throws \Exception
     */
    public function decompressFile($file)
    {
        $zipService = new Zip($this->getConnection());

        return $zipService->decompressFile($file);
    }

    /**
     * @param $file
     *
     * @throws \Exception
     */
    public function deleteFile($file)
    {
        $this->getConnection()->deleteFile($file);
    }

    /**
     * @param        $file
     * @param Server $server
     * @param        $destination
     *
     * @throws \Exception
     */
    public function transferFile($file, $destination, Server $toServer)
    {
        $task = Task::create('Rsyncing file');
        $command = 'rsync -a ' . $file . ' impero@' . $toServer->ip . ':' . $destination . ' -e \'ssh -p ' . $toServer->port . '\'';

        return $task->make(
            function() use ($command) {
                return $this->exec($command);
            }
        );
    }

    /**
     * @return Mysql
     * @throws \Exception
     */
    public function getMysqlService()
    {
        if (!$this->mysqlService) {
            $this->mysqlService = new Mysql($this->getConnection());
        }

        return $this->mysqlService;
    }

    /**
     * @param $service
     *
     * @return mixed|OpenSSL|Mysql|Backup
     * @throws \Exception
     */
    public function getService($service)
    {
        return new $service($this->getConnection());
    }

    /**
     *
     */
    public function binlogBackup()
    {
        /**
         * Check that sync script is running.
         */
        (new Mysql($this))->syncBinlog($this);
    }

}