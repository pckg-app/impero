<?php namespace Impero\Services\Service;

use Impero\Mysql\Record\Database;

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
        $backupPath = $this->prepareDirectory('mysql/backup');
        $file = $this->name . '_' . date('Ymdhis') . '_' . $database->server_id . '.sql';
        $flags = '--routines --triggers --skip-opt --order-by-primary --create-options --compact --master-data=2 --single-transaction --extended-insert --add-locks --disable-keys';

        $dumpCommand = 'mysqldump ' . $flags . ' -u ' . $user . ' ' . $this->name . ' > ' . $backupPath . $file;
        $this->getConnection()->exec($dumpCommand);

        return $file;
    }

    public function toCold($file)
    {
        /**
         * @T00D00 - Transfer image to digital ocean spaces?
         */
        $this->getConnection()->exec('rm ' . $file);
    }

}