<?php namespace Impero\User\Controller;

use Impero\User\Record\User;

class Users
{

    public function postCreateAction()
    {
        $data = only(post()->all(), ['user_group_id', 'email', 'password', 'username', 'parent']);

        $user = User::create($data);

        return [
            'user' => $user,
        ];
    }

    public function getUserAction(User $user)
    {
        return [
            'user' => $user,
        ];
    }

}