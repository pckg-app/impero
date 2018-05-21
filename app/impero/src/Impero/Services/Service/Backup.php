<?php namespace Impero\Services\Service;

use Impero\Mysql\Record\Database;
use Impero\Servers\Record\Server;
use Impero\Storage\Record\Storage;

/**
 * Class Backup
 *
 * @package Impero\Services\Service
 */
class Backup extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'backup';

    /**
     * @var string
     */
    protected $name = 'Backup';

    /**
     * @return string
     */
    public function getVersion()
    {
        return 'version todo';
    }

    /**
     * @param Database $database
     *
     * @return string
     */
    public function createMysqlBackup(Database $database)
    {
        /**
         * This commands will always executed by impero user, which is always available on filesystem.
         *
         * @T00D00 - read password from .cnf in impero home dir?
         *         - make sure that backup path exists and is writable
         */
        $user = 'impero';
        $backupFile = $this->prepareDirectory('random') . sha1random();
        $flags = '--routines --triggers --skip-opt --order-by-primary --create-options --compact --master-data=2 --single-transaction --extended-insert --add-locks --disable-keys';

        $dumpCommand = 'mysqldump ' . $flags . ' -u ' . $user . ' ' . $database->name . ' > ' . $backupFile;
        d($dumpCommand);
        $this->getConnection()->exec($dumpCommand);

        return $backupFile;
    }

    /**
     * @param Server  $server
     * @param Storage $storage
     *
     * @return mixed
     * @throws \Exception
     */
    public function createStorageBackup(Server $server, Storage $storage)
    {
        $zip = (new Zip($server->getConnection()));
        return $zip->compressDirectory($storage->location);
    }

    /**
     * @param Database $database
     * @param          $file
     */
    public function importMysqlBackup(Database $database, $file)
    {
        $output = $this->exec('mysql -u impero -e \'SHOW DATABASES WHERE `Database` = "' . $database->name . '"\'');
        if (trim($output)) {
            /**
             * Already existing.
             */
            return;
        }
        $this->exec('mysql -u impero -e \'CREATE DATABASE IF NOT EXISTS `' . $database->name . '`\'');
        $command = 'mysql -u impero ' . $database->name . ' < ' . $file;
        $this->getConnection()->exec($command);
    }

    /**
     * @param $file
     *
     * @return string
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\FileExistsException
     */
    public function toCold($file)
    {
        $do = (new DigitalOcean($this->getConnection()));
        $uploaded = $do->uploadToSpaces($file);
    }

    /**
     * @param $file
     *
     * @return string
     */
    public function fromCold($file)
    {
        $do = (new DigitalOcean($this->getConnection()));
        return $do->downloadFromSpaces($file);
    }

}