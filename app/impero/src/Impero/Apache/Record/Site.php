<?php namespace Impero\Apache\Record;

use Impero\Apache\Console\DumpVirtualhosts;
use Impero\Apache\Entity\Sites;
use Impero\Mysql\Record\Database;
use Impero\Mysql\Record\User as DatabaseUser;
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

    public function getUserPath()
    {
        return '/www/' . $this->user->username . '/';
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
            $this->server->logCommand('Skipping obtaining certificate(s) for domains ' .
                                      collect($skipped)->implode(', ') . ' on ip ' . $ip, null, null, null);
        }

        if ($realDomains) {
            $this->server->logCommand('Obtaining certificate(s) for domains ' . collect($realDomains)->implode(', ') .
                                      ' on ip ' . $ip, null, null, null);
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

    public function checkout($pckg, $password)
    {
        /**
         * If needed we enable https / letsencrypt.
         */
        $site = $this->prepareSite($pckg);

        /**
         * We checkout and initialize standalone platforms or create directory structure and symlinks for linked ones.
         */
        $this->checkoutPlatform($pckg);

        /**
         * Project is prepared on linked and standalone platforms.
         * We need to apply storage service and execute 'prepare' commands.
         * Here we create proper directories and symlinks.
         */
        $this->preparePlatform($pckg);

        /**
         * Create database, privileges, enable backups and replication.
         * Also imports clean database, creates admin user and writes configuration.
         */
        $this->prepareDatabase($pckg, $password);

        /**
         * Everything is ready, we may enable cronjobs.
         */
        $this->enableCronjobs($pckg);
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
            $connection->exec('sudo mv ' . $this->getHtdocsOldPath() . ' ' . $this->getHtdocsOlderPath());
        }

        /**
         * Move existent htdocs to htdocs-old
         */
        $connection->exec('sudo mv ' . $this->getHtdocsPath() . ' ' . $this->getHtdocsOldPath());

        /**
         * Create new htdocs path
         */
        $connection->exec('sudo mkdir ' . $this->getHtdocsPath());
    }

    public function redeploy($pckg, $vars)
    {
        $this->vars = $vars;
        $this->createNewHtdocsPath();
        $this->checkoutPlatform($pckg);
        $this->preparePlatform($pckg, true);
        $this->copyOldConfig();
        $this->enableCronjobs($pckg);
    }

    public function copyOldConfig()
    {
        $this->getServerConnection()->exec('sudo cp ' . $this->getHtdocsOldPath() . 'config/env.php ' .
                                           $this->getHtdocsPath() . 'config/env.php');
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
        $connection->execMultiple([
                                      'git clone ' . $pckg['repository'] . ' .',
                                      'git checkout ' . $pckg['branch'] . ' .',
                                  ], $errorStream, $aliasDir);

        /**
         * Init platform.
         */
        $connection->execMultiple($pckg['init'], $errorStream, $aliasDir);

        /**
         * Prepare platform.
         */
        $connection->execMultiple($pckg['prepare'], $errorStream, $aliasDir);
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
            $aliasDir = '/www/_linked/' . str_replace(['.', '@', '/', ':'], '-', $pckg['repository']) . '/' .
                        $pckg['branch'] . '/';
            $this->prepareLinkedCheckout($pckg, $aliasDir);

            /**
             * Create some dirs, such as www and cache in which we will probably mount some files.
             * We won't store any data in those directories.
             */
            foreach ($pckg['checkout']['create']['dir'] as $dir) {
                $commands[] = 'sudo mkdir ' . $this->getHtdocsPath() . $dir;
            }

            /**
             * Create dir and file symlinks for shared stuff.
             */
            foreach ($pckg['checkout']['symlink']['dir'] as $dir) {
                $commands[] = 'sudo ln -s ' . $aliasDir . $dir . ' ' . $this->getHtdocsPath() . $dir;
            }
            foreach ($pckg['checkout']['symlink']['file'] as $file) {
                $commands[] = 'sudo ln -s ' . $aliasDir . $file . ' ' . $this->getHtdocsPath() . $file;
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

    public function preparePlatform($pckg, $existanceCheck = true)
    {
        $rootCommands = [];
        $siteStoragePath = $this->getStorageDir();
        $htdocsOldPath = $this->getHtdocsOldPath();
        $connection = $this->getServerConnection();

        /**
         * Create dir.
         */
        foreach ($pckg['services']['storage']['dir'] ?? [] as $storageDir) {
            if ($existanceCheck) {
                $hasRootDir = $connection->dirExists($siteStoragePath . $storageDir);
                if ($hasRootDir) {
                    /**
                     * Storage already exists and will be mounted.
                     */
                    continue;
                }

                $hasDir = $connection->dirExists($htdocsOldPath . $storageDir);
                if ($hasDir) {
                    /**
                     * Existing dirs are copied to storage server.
                     * Recreation is skipped.
                     */
                    $rootCommands[] = 'sudo mkdir -p ' . $siteStoragePath . $storageDir;
                    $rootCommands[] = 'sudo rsync -a ' . $htdocsOldPath . $storageDir . ' ' . $siteStoragePath .
                                      $storageDir . ' --stats';
                    continue;
                }

                $hasSymlink = $connection->symlinkExists($htdocsOldPath . $storageDir);
                if ($hasSymlink) {
                    /**
                     * Storage doesn't exist in storage, doesn't exist in old and was symlinked.
                     * It was probably linked to some other location?
                     */
                } else {
                    /**
                     * Storage is totally new, create it.
                     */
                }
            }

            /**
             * Create $storageDir directory in site's directory on storage server.
             */
            $rootCommands[] = 'sudo mkdir -p ' . $siteStoragePath . $storageDir;
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
            $rootCommands[] = 'sudo ln -s ' . $originPoint . ' ' . $this->getHtdocsPath() . $linkPoint;
        }

        /**
         * Sync old files, create new directories and symlinks.
         */
        $connection->execMultiple($rootCommands);

        /**
         * Execute prepare commands.
         */
        $errorStreamContext = null;
        $connection->execMultiple($pckg['prepare'], $errorStreamContext, $this->getHtdocsPath());
    }

    public function replaceVars($command)
    {
        /**
         * @T00D00 - get identifier and app
         */
        $defaults = [
            '$webDir'     => $this->getHtdocsPath(),
            '$logsDir'    => $this->getLogPath(),
            '$storageDir' => $this->getStorageDir(),
        ];
        $replaces = array_merge($defaults, $this->vars);

        return str_replace(array_keys($replaces), $replaces, $command);
    }

    public function createFile($file, $content)
    {
        $this->getServerConnection()->sftpSend($content, $this->getHtdocsPath() . $file, null, false);
    }

    public function prepareDatabase($pckg, $password)
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
            $dbuser = $dbname = null;

            if ($config['type'] == 'searchOrCreate') {
                $dbname = $dbuser = $this->replaceVars($config['name']);

                /**
                 * Create mysql database, user and set privileges.
                 */
                $database = Database::createFromPost([
                                                         'name'      => $dbname,
                                                         'server_id' => 2,
                                                     ]);

                /**
                 * Manually call backup and replication.
                 */
                $database->backup();
                $database->replicate();

                /**
                 * Create initial database.
                 *
                 * @T00D00 - migrations should take care of this ...
                 */
                $database->importFile(['file' => '/www/clean_derive.sql']);

                /**
                 * Create admin user account.
                 *
                 * @T00D00 - this is per project?
                 */
                $userPass = $password ?? auth()->createPassword(10);
                $database->query('INSERT INTO users (status_id, password, email, name, surname, enabled, language_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                                 [
                                     1,
                                     password_hash($userPass, PASSWORD_DEFAULT),
                                     $this->client->email,
                                     $this->client->name,
                                     $this->client->surname,
                                     1,
                                     'en',
                                 ]);

                /**
                 * Copy configuration
                 *
                 * @T00D00
                 */
                $this->createFile('config/env.php', $this->getConfigContent($dbname, $dbuser, $dbpass));
            } else if ($config['type'] == 'search') {
                $database = Database::gets([
                                               'server_id' => 2,
                                               'name'      => $config['name'],
                                           ]);
            }

            /**
             * Check for access.
             */
            if ($dbuser && $database && isset($pckg['services']['db']['mysql']['user']['access'][$key])) {
                DatabaseUser::createFromPost([
                                                 'username'  => $dbuser,
                                                 'password'  => $dbpass,
                                                 'server_id' => 2,
                                                 'database'  => $database->id,
                                                 'privilege' => $pckg['services']['db']['mysql']['user']['access'][$key],
                                             ]);
            }
        }
    }

}
