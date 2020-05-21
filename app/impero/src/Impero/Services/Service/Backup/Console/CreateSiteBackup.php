<?php namespace Impero\Services\Service\Backup\Console;

use Impero\Apache\Entity\Sites;
use Impero\Apache\Entity\SitesServers;
use Impero\Apache\Record\Site;
use Impero\Mysql\Record\Database;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Impero\Services\Service\Backup;
use Pckg\Framework\Console\Command;
use Pckg\Queue\Service\Cron\Fork;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Class CreateSiteBackup
 *
 * @package Impero\Services\Service\Backup\Console
 */
class CreateSiteBackup extends Command
{

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('service:backup:site')
            ->setDescription('Make cold backup of databases, storage, SSL and configuration')
            ->addOptions([
                'site' => 'Site name or ID',
            ], InputOption::VALUE_REQUIRED);
    }

    /**
     *
     */
    public function handle()
    {
        /**
         * Storage
         *  - htdocs
         *  - logs?
         * Mysql
         *  - project databases
         * Config
         *  - config/env.php
         *  - .env
         * Certificates
         *  - /etc/letsencrypt/
         * Upload everything to DO spaces.
         */
        $site = Site::getOrFail($this->option('site'));
        $server = $site->server;

        if (!$this->askConfirmation('Site ' . $site->server_name . '?')) {
            return;
        }

        /**
         * We can do the same with database.
         */
        $databases = (new SitesServers())->whereArr(['site_id' => $site->id, 'type' => 'database'])->all();
        if ($databases->count() !== 1) {
            $this->outputDated('No databases or not one');
        } else {
            $this->outputDated('Has only one database, creating database backup');
            $database = $databases->first();
            ddd($database->data());
            /**
             * Now we need to get the name of database?
             */
            $coldDatabase = Backup::fullColdBackup(new Backup($server), function (Backup $backupService) use ($database) {
                return $backupService->createMysqlBackup($database);
            }, $server, [
                'morph_id' => Sites::class,
                'poly_id' => $site->id,
                'type' => 'mysql:dump',
            ]);
            $this->outputDated('Database backup created');
        }

        /**
         * The problem is - our storage is currently shared between all projects on zero.
         * Now it's time to split it. We need to upload it to spaces.
         * ... and finally solve the upload and AWS/D3 credentials. Now, there are few cases.
         *
         * First case: Medium is on foobar.si server. We will move it to new medium.foobar.si server.
         * We need to backup the storage and database. Medium does not have a linked storage volume.
         * So we create a backup and upload it to spaces.
         */
        $this->outputDated('Creating storage backup');
        $coldStorage = Backup::fullColdBackup(new Backup($server), function (Backup $backupService) use ($site) {
            return $backupService->createDirectoryBackup($site->getHtdocsPath() . 'storage');
        }, $server, [
            'morph_id' => Sites::class,
            'poly_id' => $site->id,
            'type' => 'storage:dump',
        ]);
        $this->outputDated('Storage backup crated');

        /**
         * And we need to do the same with certificates.
         * This is a bit more complicated since we create backups from 3 folders (live, certs, config?).
         * And extract them later.
         * Current certificate (with latest domains) is actually symlinked to ssl.
         */
        $this->outputDated('Creating SSL backup');
        $coldCertficates = Backup::fullColdBackup(new Backup($server), function (Backup $backupService) use ($site) {
            return $backupService->createSslBackup($site->getSslPath(), $site->getSslDomain());
        }, $server, [
            'morph_id' => Sites::class,
            'poly_id' => $site->id,
            'type' => 'ssl:dump',
        ]);
        $this->outputDated('SSL backup created');
    }

}