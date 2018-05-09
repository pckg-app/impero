<?php namespace Impero\Mysql\Controller;

use Impero\Mysql\Record\Database;
use Pckg\Framework\Controller;

class DatabaseApi extends Controller
{

    public function postDatabaseAction()
    {
        /**
         * Receive posted data.
         */
        $data = post(['name', 'server_id']);

        /**
         * Create datebase.
         */
        $database = Database::createFromPost($data);

        /**
         * Return created database.
         */
        return ['database' => $database];
    }

    public function postImportFileAction(Database $database)
    {
        $file = post('file');

        $database->importFile($file);

        return $this->response()->respondWithSuccess();
    }

    public function postSearchAction()
    {
        $server = post('server_id');
        $databaseName = post('name');

        $database = Database::gets([
            'server_id' => $server,
            'name'      => $databaseName,
        ]);

        return [
            'database' => $database,
        ];
    }

    public function postQueryAction(Database $database)
    {
        $sql = post('sql');
        $bind = post('bind');

        $data = $database->query($sql, $bind);

        return [
            'data' => $data,
        ];
    }

    /**
     * @param Database $database
     *
     * @return array
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function getBackupAction(Database $database)
    {
        return null;
        return $this->postBackupAction($database);
    }

    /**
     * @param Database $database
     *
     * @return array
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Throwable
     */
    public function postBackupAction(Database $database)
    {
        /**
         * Enable or disable mysql backup.
         */
        $database->backup();

        return [
            'success' => true,
        ];
    }

    /**
     * @param Database $database
     *
     * @return array
     * @throws \Exception
     */
    public function postReplicateAction(Database $database)
    {
        /**
         * Enable or disable mysql replication.
         */
        $database->requireMysqlMasterReplication();
        $database->replicateOnMaster();

        return [
            'success' => true,
        ];
    }

}
