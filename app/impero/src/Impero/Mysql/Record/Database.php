<?php namespace Impero\Mysql\Record;

use Impero\Mysql\Entity\Databases;
use Pckg\Database\Record;

class Database extends Record
{

    protected $entity = Databases::class;

    /**
     * Build edit url.
     *
     * @return string
     */
    public function getEditUrl()
    {
        return url('database.edit', ['database' => $this]);
    }

    /**
     * Build delete url.
     *
     * @return string
     */
    public function getDeleteUrl()
    {
        return url('database.delete', ['database' => $this]);
    }

    public function setUserIdByAuthIfNotSet()
    {
        if (!$this->user_id) {
            $this->user_id = auth()->user('id');
        }

        return $this;
    }

    public function configureBackup()
    {
        /**
         * Get current backup configuration.
         */
        $backupFile = '/backup/dbarray.conf';
        $connection = $this->server->getConnection();
        $currentBackup = $connection->sftpRead($backupFile);
        $databases = explode("\n", $currentBackup);

        /**
         * Check for existance.
         */
        if (!in_array($this->name, $databases)) {
            /**
             * Add to file if nonexistent.
             */
            $connection->exec('sudo echo "' . "\n\r" . '' . $this->name . '" >> ' . $backupFile);
        }
    }

}
