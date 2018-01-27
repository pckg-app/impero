<?php namespace Impero\Apache\Record;

use Impero\Apache\Console\DumpVirtualhosts;
use Impero\Apache\Entity\Sites;
use Impero\Services\Service\SshConnection;
use Pckg\Database\Record;

class Site extends Record
{

    protected $entity = Sites::class;

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
        /**
         * Get current cronjob configuration.
         */
        $cronjobFile = '/backup/run-cronjobs.sh';
        $connection = $this->getServerConnection();
        $currentCronjob = $connection->sftpRead($cronjobFile);
        $cronjobs = explode("\n", $currentCronjob);

        /**
         * Check for existance.
         */
        if (!in_array($command, $cronjobs)) {
            /**
             * Add to file if nonexistent.
             */
            $connection->exec('sudo echo "' . $command . '" >> ' . $cronjobFile);
        }
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
        $domains = collect([$domain])->pushArray(explode(' ', $this->document_root))->unique()->removeEmpty();

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
        $realDomains = implode(',', $realDomains);
        $params = '--agree-tos --non-interactive --text --rsa-key-size 4096 --email ' . $email
                  . ' --webroot-path ' . $webroot . ' --cert-name ' . $domain . ' --domains "'
                  . $realDomains . '" --webroot --expand';

        if ($skipped) {
            $this->server->logCommand('Skipping obtaining certificate(s) for domains ' .
                                      collect($skipped)->implode(', ') . ' on ip ' . $ip, null, null, null);
        }

        if ($realDomains) {
            $this->server->logCommand('Obtaining certificate(s) for domains ' . collect($realDomains)->implode(', ') .
                                      ' on ip ' . $ip, null, null, null);
        }

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

}
