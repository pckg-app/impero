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
        return ServerCommand::create(
            [
                'server_id'   => $this->id,
                'command'     => $command,
                'info'        => $info,
                'error'       => ($e ? 'EXCEPTION: ' . exception($e) . "\n" : null) .
                    $error,
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
        $currentCronjob = $connection->sftpRead($cronjobFile);
        $cronjobs = explode("\n", $currentCronjob);

        /**
         * Check for existance.
         */
        if (!in_array($command, $cronjobs) && !in_array('#' . $command, $cronjobs)) {
            /**
             * Add to file if nonexistent.
             */
            $connection->exec('sudo echo "' . $command . '" >> ' . $cronjobFile);
        }
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
            return $eth1->value;
        }

        /**
         * Get IP from public network interface.
         */
        $eth0 = $this->getSettingValue('server.network.eth0.ip');

        if ($eth0) {
            return $eth0->value;
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
        /**
         * Mysql config is separated into:
         */
        // /backup/dbarray.conf
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
        $sites = (new SitesServers())->where('server_id', $this->id)
                                     ->where('type', 'web')
                                     ->all();

        $server = $this;
        $sites->each(
            function(SitesServer $sitesServer) use (&$virtualhosts, &$virtualhostsNginx, $server) {
                /**
                 * Apache: apache port
                 * Nginx: nginx port
                 * Haproxy: haproxy port
                 */
                $virtualhosts[] = $sitesServer->site->getVirtualhost($server);
            }
        );

        return implode($virtualhosts);
    }

    public function getHaproxyConfig()
    {
        /**
         * First, check that apache is active on server.
         */
        $active = $this->getSettingValue('service.haproxy.active');
        if (!$active) {
            return null;
        }

        /**
         * Get all sites that are routed to this server and proxied to workers.
         */
        $sites = $this->sites;

        $httpPort = $this->getSettingValue('service.haproxy.httpPort', 80);
        $httpsPort = $this->getSettingValue('service.haproxy.httpsPort', 80);

        $config = 'frontend http2https
    bind *:' . $httpPort . '
    redirect scheme https code 301 if !{ ssl_fc }
    
frontend all_https
    bind *:' . $httpsPort . '
    mode tcp
    option tcplog';

        foreach ($sites as $site) {
            $domains = $site->getUniqueDomains();
            //$replaced = str_replace(['.', '-'], ['\.', '\-'], implode('|', $domains));
            //$config .= "\n" . '    acl bcknd-' . $site->id . ' hdr_reg(host) -i ^(' . $replaced . ')$';
            $config .= "\n" . '    acl bcknd-' . $site->id . ' hdr(host) -i ' . implode(' ', $domains);
            $config .= "\n" . '    use_backend backend-' . $site->id . ' if bcknd-' . $site->id;
        }
        //$config .= "\n" . '    default_backend b';

        foreach ($sites as $site) {
            /**
             * Receive list of all server that site is deployed to.
             */
            $workers = $site->getServiceServers('web');

            $config .= "\n" . 'backend backend-' . $site->id;
            $config .= "\n" . '    balance roundrobin';
            $config .= "\n" . '    mode tcp';
            foreach ($workers as $worker) {
                $workerHttpsPort = $worker->getSettingValue('service.apache2.httpsPort', 443);
                $config .= "\n" . '    server ' . $worker->name . ' ' . $worker->privateIp . ':' . $workerHttpsPort .
                    ' check';
            }
        }

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
    public function decompressFile($file, $output)
    {
        $zipService = new Zip($this->getConnection());

        return $zipService->decompressFile($file, $output);
    }

    /**
     * @param $file
     *
     * @throws \Exception
     */
    public function decryptFile($file, $output, $keyFiles)
    {
        /**
         * Transfer key file from impero to server.
         */
        $keyDir = implode('/', array_slice(explode('/', $keyFiles['private']), 0, -1));
        if (!$this->getConnection()->dirExists($keyDir)) {
            $this->getConnection()->exec('mkdir -p ' . $keyDir);
        }
        $this->getConnection()->sftpSend($keyFiles['private'], $keyFiles['private']);

        /**
         * Decrypt file with openssl private key.
         */
        $command = 'openssl rsautl -decrypt -inkey ' . $keyFiles['private'] . ' -in ' . $file . ' -out ' . $output;
        $this->exec($command);

        /**
         * Delete encryption key from server, impero holds the only copy of key.
         */
        //$this->deleteFile($keyFile);
    }

    /**
     * @param $file
     *
     * @throws \Exception
     */
    public function deleteFile($file)
    {
        $command = 'rm ' . $file;
        $this->exec($command);
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
        $command = 'rsync -a ' . $file . ' impero@' . $toServer->ip . ':' . $destination;
        $this->exec($command);
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

}