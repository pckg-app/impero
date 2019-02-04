<?php namespace Impero\Task\Provider;

use Impero\Task\Controller\Tasks;
use Pckg\Framework\Provider;

class Task extends Provider
{

    public function routes()
    {
        return [
            routeGroup([
                           'controller' => Tasks::class,
                       ], [
                           'tasks.history' => route('/tasks', 'tasks'),
                       ]),

            routeGroup([
                           'controller' => Tasks::class,
                           'namePrefix' => 'api.tasks',
                           'urlPrefix'  => '/api',
                       ], [
                           '' => route('/tasks', 'apiTasks'),
                       ]),
        ];
    }

}