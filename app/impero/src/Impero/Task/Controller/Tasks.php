<?php namespace Impero\Task\Controller;

use Impero\Servers\Record\ServerCommand;
use Impero\Servers\Record\Task;

class Tasks
{

    public function getTasksAction()
    {
        vueManager()->addView('Impero/Task:tasks');

        return '<impero-tasks></impero-tasks>';
    }

    public function getApiTasksAction()
    {
        $transform = function(Task $task, callable $transform) {
            return [
                'id'         => $task->id,
                'title'      => $task->title,
                'status'     => $task->status,
                'started_at' => $task->started_at,
                'ended_at'   => $task->ended_at,
                'tasks'      => collect($task->tasks)->map(function(Task $task) use ($transform) {
                    return $transform($task, $transform);
                }),
                'commands'   => $task->serverCommands->map(function(ServerCommand $serverCommand) {
                    return [
                        'command'     => $serverCommand->command,
                        'info'        => $serverCommand->info,
                        'error'       => $serverCommand->error,
                        'executed_at' => $serverCommand->executed_at,
                    ];
                }),
            ];
        };

        return [
            'tasks' => (new \Impero\Servers\Entity\Tasks())->withServerCommands()
                                                           ->where('started_at',
                                                                   date('Y-m-d H:i', strtotime('-36hours')), '>')
                                                           ->orderBy('id ASC')
                                                           ->all()
                                                           ->tree('parent_id', 'id', 'tasks')
                                                           ->sortBy(function(Task $task) {
                                                               return -$task->id;
                                                           })
                                                           ->map(function(Task $item) use ($transform) {
                                                               return $transform($item, $transform);
                                                           })
                                                           ->toArray(),
        ];
    }

}