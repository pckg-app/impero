<?php namespace Impero\Apache\Record;

use Impero\Apache\Console\DumpVirtualhosts;
use Impero\Apache\Entity\Sites;
use Pckg\Database\Record;

class Site extends Record
{

    protected $entity = Sites::class;

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
        $connection = $this->server->getConnection();

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
        $connection = $this->server->getConnection();
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
        $webroot = '/var/www/default/html';
        $domain = $this->server_name;
        $domains = collect($domain)->pushArray(explode(' ', $this->document_root))->removeEmpty()->implode(',');
        $params = '--agree-tos --non-interactive --text --rsa-key-size 4096 --email ' . $email
                  . ' --webroot-path ' . $webroot . ' --cert-name ' . $domain . ' --domains "'
                  . $domains . '" --apache --expand';

        /**
         * Execute command.
         */
        $connection = $this->server->getConnection();
        $connection->exec($command . ' ' . $params);

        /**
         * If command is successful update site, dump config and restart apache.
         */
        /*$this->setAndSave([
                              'ssl'                        => 'letsencrypt',
                              'ssl_certificate_file'       => 'cert.pem',
                              'ssl_certificate_key_file'   => 'privkey.pem',
                              'ssl_certificate_chain_file' => 'fullchain.pem',
                              'ssl_letsencrypt_autorenew'  => true,
                          ]);
        $this->restartApache();*/
    }

    public function restartApache()
    {
        (new DumpVirtualhosts())->executeManually(['--server' => $this->server_id]);
    }

}
