<?php namespace Impero\Task\Controller;

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
            ];
        };

        return [
            'tasks' => (new \Impero\Servers\Entity\Tasks())->where('started_at',
                                                                   date('Y-m-d', strtotime('-3days')),
                                                                   '>')
                                                           ->orderBy('id DESC')
                                                           ->all()
                                                           ->tree('parent_id', 'id', 'tasks')
                                                           ->map(function(Task $item) use ($transform) {
                                                               return $transform($item, $transform);
                                                           })
                                                           ->toArray(),
        ];
    }

}