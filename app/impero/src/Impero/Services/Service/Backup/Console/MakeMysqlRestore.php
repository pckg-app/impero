<?php namespace Impero\Services\Service\Backup\Console;

use Impero\Apache\Entity\Sites;
use Impero\Apache\Entity\SitesServers;
use Impero\Mysql\Entity\Databases;
use Impero\Mysql\Record\Database;
use Impero\Secret\Entity\Secrets;
use Impero\Secret\Record\Secret;
use Impero\Servers\Entity\Servers;
use Impero\Servers\Record\Server;
use Impero\Services\Service\Backup;
use Impero\Services\Service\Mysql;
use Impero\Services\Service\Zip;
use Pckg\Database\Query;
use Pckg\Framework\Console\Command;
use Pckg\Generic\Entity\SettingsMorphs;
use Pckg\Queue\Service\Cron\Fork;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Class MakeMysqlBackup
 *
 * @package Impero\Services\Service\Backup\Console
 */
class MakeMysqlRestore extends Command
{

    /**
     * @throws \Exception
     */
    public function handle()
    {
        $this->outputDated('Checking site parameter');
        $site = $this->option('site');
        required($site, '', 'Site parameter is required');
        $siteRecord = (new Sites())->where('server_name', $site)->oneOrFail();
        $domains = stringify($siteRecord->server_alias)
            ->explodeToCollection(' ')
            ->prepend($siteRecord->server_name)
            ->unique()
            ->removeEmpty()
            ->implode(' ');
        if (!$this->askConfirmation('Site #' . $siteRecord->id . ' ' . $domains . ' from ' . $siteRecord->user->email .
            '?', false)) {
            $this->outputDated('Exit');

            return;
        }
        $this->outputDated('Getting settings');
        $setting = (new SettingsMorphs())->where('morph_id', Sites::class)
            ->where('poly_id', $siteRecord->id)
            ->where('setting_id', 12)
            ->oneOrFail();
        $id = json_decode($setting->value, true)['$identifier'];
        $database = Database::getOrFail(['name' => $id . '_shop']);
        if (!$this->askConfirmation('Database #' . $database->id . ' ' . $database->name . '?', false)) {
            $this->outputDated('Exit');

            return;
        }
        $name = $database->name;
        $secrets = (new Secrets())->where('secrets.keys->"$.morph_id"', [Databases::class])
            ->where('secrets.keys->>"$.poly_id"', [$database->id]) // JSON UNQUOTE :/
            ->where('secrets.keys->"$.type"', ['mysql:dump'])
            ->orderBy('secrets.id DESC')
            ->all();
        if ($secrets->count() === 0) {
            $this->outputDated('No backup found');

            return;
        }

        $this->outputDated($secrets->count() . ' backup(s) found for database ' . $name);
        $dates = $secrets->map(function (Secret $secret) {
            return json_decode($secret->keys)->created_at;
        });
        $min = $dates->min();
        $max = $dates->max();
        if ($min !== $max) {
            $this->outputDated('From ' . $min . ' to ' . $max);
        }
        $this->outputDated($secrets->slice(0, 10)->map(function (Secret $secret) {
            return '#' . $secret->id . ' @ ' . json_decode($secret->keys)->created_at;
        })->implode("\n"));

        /**
         * Locate correct backup.
         */
        $date = $this->askQuestion('Enter nearest date to restore');
        $sorted = $secrets->sortBy(function (Secret $secret) use ($date) {
            return abs(strtotime($date) - strtotime(json_decode($secret->keys)->created_at));
        });
        $nearest = $sorted->first();
        $file = $nearest->file;
        $date = json_decode($nearest->keys)->created_at;

        /**
         * Okay, we have the file name, we can extract it and import it to hi_shop_
         */
        $copyName = $name . '_rest_' . substr(sha1(microtime()), 0, 8);
        $message = 'Extract ' . $file . ' from ' . $date . ', import it to ' . $copyName . '?';
        $confirm = $this->askQuestion($message, null);
        if (!$confirm) {
            $this->outputDated('Exit');

            return;
        }

        /**
         * A helper that will download file from spaces, decrypt and extract it.
         * Then we create database and pipe the file in.
         */
        Backup::fullColdRestore((new Backup($siteRecord->server)), $nearest, function ($file) use ($copyName, $siteRecord) {
            /**
             * Create mysql database and import it on the same server.
             */
            $mysql = new Mysql($siteRecord->server);
            $mysql->restoreDatabase($copyName, $file);
        }, $siteRecord->server);

        $this->outputDated('Restored?');
    }

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('service:mysql:restore')
            ->setDescription('Restore a mysql backup')
            ->addOptions(['site' => 'Site domain'], InputOption::VALUE_REQUIRED);
    }

}