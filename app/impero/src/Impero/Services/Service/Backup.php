<?php namespace Impero\Services\Service;

use Impero\Mysql\Record\Database;
use Impero\Services\Services\DigitalOcean;

class Backup extends AbstractService implements ServiceInterface
{

    protected $service = 'backup';

    protected $name = 'Backup';

    public function getVersion()
    {
        return 'version todo';
    }

    public function createMysqlBackup(Database $database)
    {
        /**
         * This commands will always executed by impero user, which is always available on filesystem.
         *
         * @T00D00 - read password from .cnf in impero home dir?
         *         - make sure that backup path exists and is writable
         */
        $user = 'impero';
        $backupFile = $this->prepareDirectory('mysql/backup') . sha1random();
        $flags = '--routines --triggers --skip-opt --order-by-primary --create-options --compact --master-data=2 --single-transaction --extended-insert --add-locks --disable-keys';

        $dumpCommand = 'mysqldump ' . $flags . ' -u ' . $user . ' ' . $database->name . ' > ' . $backupFile;
        $this->getConnection()->exec($dumpCommand);

        return $backupFile;
    }

    public function importMysqlBackup(Database $database, $file)
    {
        $command = 'mysql -u impero ' . $database->name . ' < ' . $file;
        $this->getConnection()->exec($command);
    }

    /**
     * @param $file
     *
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\FileExistsException
     */
    public function toCold($file)
    {
        $do = (new DigitalOcean($this->getConnection()));
        return $do->uploadToSpaces($file);
    }

    public function fromCold($file)
    {
        $do = (new DigitalOcean($this->getConnection()));
        return $do->downloadFromSpaces($file);
    }

}