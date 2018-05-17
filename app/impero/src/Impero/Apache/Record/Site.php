<?php namespace Impero\Apache\Record;

use Defuse\Crypto\Key;
use Impero\Apache\Console\DumpVirtualhosts;
use Impero\Apache\Entity\Sites;
use Impero\Mysql\Record\Database;
use Impero\Mysql\Record\User as DatabaseUser;
use Impero\Servers\Record\Server;
use Impero\Services\Service\SshConnection;
use Pckg\Database\Record;

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

    public function createOnFilesystem()
    {
        $connection = $this->getServerConnection();

        $connection->exec('mkdir -p ' . $this->getHtdocsPath());
        $connection->exec('mkdir -p ' . $this->getLogPath());
        $connection->exec('mkdir -p ' . $this->getSslPath());
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

    public function getVirtualhost()
    {
        return $this->getInsecureVirtualhost() . "\n\n" . $this->getSecureVirtualhost();
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

    public function getInsecureVirtualhost()
    {
        $directives = $this->getBasicDirectives();

        $return = '<VirtualHost *:80>' . "\n\t" . implode("\n\t", $directives) . "\n" . '</VirtualHost>';

        return $return;
    }

    public function getSecureVirtualhost()
    {
        if (!$this->ssl) {
            return;
        }

        $directives = $this->getBasicDirectives();
        $directives[] = 'SSLEngine on';
        $directives[] = 'SSLCertificateFile ' . $this->getSslPath() . $this->ssl_certificate_file;
        $directives[] = 'SSLCertificateKeyFile ' . $this->getSslPath() . $this->ssl_certificate_key_file;
        $directives[] = 'SSLCertificateChainFile ' . $this->getSslPath() . $this->ssl_certificate_chain_file;

        return '<VirtualHost *:443>
    ' . implode("\n\t", $directives) . '
</VirtualHost>';
    }

    public function addCronjob($command)
    {
        $this->server->addCronjob($command);
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
        $domains = collect([$domain])->pushArray(explode(' ', $this->server_alias))->unique()->removeEmpty();

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

        if (strpos($response, 'Congratulations! Your certificate and chain have been saved at:') === false
            && strpos($response, 'Certificate not yet due for renewal') === false
        ) {
            return false;
        }

        /**
         * If command is successful update site, dump config and restart apache.
         */
        $dir = '/etc/letsencrypt/live/' . $domain . '/';

        /**
         * Create symlinks.
         */
        $files = ['cert.pem', 'privkey.pem', 'fullchain.pem'];
        $sslPath = $this->getSslPath();
        foreach ($files as $file) {
            if ($connection->symlinkExists($sslPath . $file)) {
                continue;
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

    public function checkout($pckg, $vars)
    {
        $this->vars = $vars;

        /**
         * If needed we enable https / letsencrypt.
         */
        $this->prepareSite($pckg);

        /**
         * We checkout and initialize standalone platforms or create directory structure and symlinks for linked ones.
         */
        $this->checkoutPlatform($pckg);

        /**
         * Project is prepared on linked and standalone platforms.
         * We need to apply storage service and execute 'prepare' commands.
         * Here we create proper directories and symlinks.
         */
        $this->preparePlatformDirs($pckg);

        /**
         * Create database, privileges, enable backups and replication.
         * Also imports clean database, creates admin user and writes configuration.
         */
        $this->prepareDatabase($pckg);

        $this->copyConfig($pckg);

        /**
         * We probably need to import few things.
         */
        $this->preparePlatform($pckg);

        /**
         * Everything is ready, we may enable cronjobs.
         */
        $this->enableCronjobs($pckg);
    }

    public function preparePlatform($pckg)
    {
        /**
         * Execute prepare commands.
         */
        $commands = [];
        foreach ($pckg['prepare'] ?? [] as $command) {
            $commands[] = $this->replaceVars($command);
        }
        $connection = $this->getServerConnection();
        $connection->execMultiple($commands);
    }

    public function copyConfig($pckg)
    {
        foreach ($pckg['checkout']['config'] ?? [] as $dest => $copy) {
            $this->createFile($dest, $this->getConfigContent());
        }
    }

    /**
     * @param     $pckg
     *
     * @return Site
     */
    public function prepareSite($pckg)
    {
        /**
         * Enable https on website.
         * This will call letsencrypt and ask for new certificate.
         * It will also add ssl virtualhost and restart apache.
         */
        if (isset($pckg['services']['web']['https'])) {
            $this->letsencrypt();
        }
    }

    public function createNewHtdocsPath()
    {
        $connection = $this->getServerConnection();

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

    public function recheckout($pckg, $vars)
    {
        $this->vars = $vars;
        $this->prepareSite($pckg);
        $this->createNewHtdocsPath();
        $this->checkoutPlatform($pckg);
        $this->preparePlatformDirs($pckg);
        $this->copyOldConfig();

        /**
         * Everything is ready, we may enable cronjobs.
         */
        $this->enableCronjobs($pckg);
    }

    public function deploy($pckg, $vars, $isAlias = false, $checkAlias = false)
    {
        $this->vars = $vars;
        $connection = $this->getServerConnection();
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
            foreach ($pckg['deploy'] ?? [] as $command) {
                $connection->exec($this->replaceVars($command), $errorStream, $deployDir);
            }
        }

        /**
         * Standalone and aliased platforms are migrated in their htdocs directory.
         */
        foreach ($pckg['migrate'] ?? [] as $command) {
            $connection->exec($this->replaceVars($command), $errorStream, $htdocsDir);
        }
    }

    public function copyOldConfig()
    {
        $this->getServerConnection()->exec(
            'cp ' . $this->getHtdocsOldPath() . 'config/env.php ' .
            $this->getHtdocsPath() . 'config/env.php'
        );
    }

    public function enableCronjobs($pckg)
    {
        /**
         * Add cronjob, we also perform uniqueness check.
         */
        foreach ($pckg['services']['cron']['commands'] ?? [] as $cron) {
            $this->server->addCronjob($this->replaceVars($cron['command']));
        }
    }

    public function prepareLinkedCheckout($pckg, $aliasDir)
    {
        $connection = $this->getServerConnection();
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
            ], $errorStream, $aliasDir
        );

        /**
         * Init platform.
         */
        $connection->execMultiple($pckg['init'], $errorStream, $aliasDir);
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

    public function checkoutPlatform($pckg)
    {
        /**
         * All commands will be executed in platform's htdocs path.
         */
        $connection = $this->getServerConnection();
        $commands = [];

        if ($pckg['checkout']['type'] == 'linked') {
            /**
             * We need to make sure that repository and branch are already checked-out on filesystem.
             */
            $aliasDir = $this->getLinkedDir($pckg);
            $this->prepareLinkedCheckout($pckg, $aliasDir);

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
            $connection->execMultiple($commands, $errorStream, $this->getHtdocsPath());
        }
    }

    public function getStorageDir()
    {
        /**
         * @T00D00 - storage path stays hardcoded for now.
         * We will get it by selecting storage server for platform on platform creation.
         */
        return '/mnt/volume-fra1-01/live/' . $this->user->username . '/' . $this->document_root . '/';
    }

    public function preparePlatformDirs($pckg)
    {
        $siteStoragePath = $this->getStorageDir();
        $htdocsOldPath = $this->getHtdocsOldPath();
        $connection = $this->getServerConnection();

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
            if ($hasOldDir) {
                /**
                 * Existing dirs are copied to storage server.
                 * Recreation is skipped.
                 */
                $connection->makeAndAllow($siteStoragePath . $storageDir);

                /**
                 * Transfer contents.
                 */
                $connection->exec(
                    'rsync -a ' . $htdocsOldPath . $storageDir . '/ ' . $siteStoragePath .
                    $storageDir . '/ --stats'
                );
                continue;
            }

            /**
             * Create $storageDir directory in site's directory on storage server.
             */
            $connection->makeAndAllow($siteStoragePath . $storageDir);
        }

        /**
         * Create symlink.
         * Also, check if dir exists
         */
        foreach ($pckg['services']['web']['mount'] ?? [] as $linkPoint => $storageDir) {
            /**
             * If mount point was previously created, it will be recreated.
             * If it was directory and it does
             */
            $originPoint = $this->replaceVars($storageDir);
            $connection->exec('ln -s ' . $originPoint . ' ' . $this->getHtdocsPath() . $linkPoint);
        }
    }

    public function replaceVars($command, $vars = [])
    {
        $defaults = [
            '$webDir'     => $this->getHtdocsPath(),
            '$logsDir'    => $this->getLogPath(),
            '$storageDir' => $this->getStorageDir(),
        ];
        $replaces = array_merge($defaults, $vars);
        $replaces = array_merge($replaces, $this->vars);

        $escaped = [];
        foreach ($replaces as $key => $value) {
            $escaped[] = $key != 'password'
                ? $value
                : escapeshellarg($value);
        }

        return str_replace(array_keys($replaces), $escaped, $command);
    }

    public function createFile($file, $content)
    {
        $this->getServerConnection()->sftpSend($content, $this->getHtdocsPath() . $file, null, false);
    }

    public function prepareDatabase($pckg)
    {
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
                        'server_id' => 2,
                    ]
                );

                /**
                 * Manually call backup and replication.
                 */
                $database->backup();
                $database->replicate();

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
                        'server_id' => 2,
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
                if (!isset($this->vars['$dbuser'])) {
                    $this->vars['$dbuser'] = $dbuser;
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

    public function getConfigContent()
    {
        /**
         * Create new security key for passwords and hashes.
         */
        $this->vars['$securityKey'] = Key::createNewRandomKey()->saveToAsciiSafeString();

        return $this->replaceVars(
            '<?php

return [
    \'identifier\' => \'$identifier\',
    \'domain\'     => \'$domain\',
    \'database\'   => [
        \'default\' => [
            \'user\' => \'$dbuser\',
            \'pass\' => \'$dbpass\',
            \'db\'   => \'$dbname\',
        ],
        \'dynamic\' => [
            \'user\' => \'$dbuser\',
            \'pass\' => \'$dbpass\',
        ],
    ],
    \'security\'   => [
        \'key\' => \'$securityKey\',
    ],
    \'pckg\'       => [
        \'mailo\' => [
            \'apiKey\' => \'$mailoApiKey\',
        ],
        \'pendo\' => [
            \'apiKey\' => \'$pendoApiKey\',
        ],
    ],
    \'router\'     => [
        \'apps\' => [
            \'$app\' => [
                \'host\' => [
                    \'(.*)\', // allow any host
                ],
            ],
        ],
    ],
];
'
        );
    }

    public function hasServiceOnServer(Server $server, $service)
    {
        if ($service == 'apache') {
            return true;
        }
    }

}
