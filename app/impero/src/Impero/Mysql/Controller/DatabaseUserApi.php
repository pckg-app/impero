<?php namespace Impero\Mysql\Controller;

use Impero\Mysql\Record\User;
use Pckg\Framework\Controller;

class DatabaseUserApi extends Controller
{

    public function postUserAction()
    {
        /**
         * Receive posted data.
         */
        $data = post(['username', 'password', 'server_id', 'database', 'privilege']);

        $user = User::createFromPost($data);

        return [
            'databaseUser' => $user,
        ];
    }

}
