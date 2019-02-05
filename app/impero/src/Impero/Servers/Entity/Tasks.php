<?php namespace Impero\Servers\Entity;

use Impero\Servers\Record\Task;
use Pckg\Database\Entity;

class Tasks extends Entity
{

    protected $record = Task::class;

    public function parent()
    {
        return $this->belongsTo(Tasks::class)->foreignKey('parent_id');
    }

    public function serverCommands()
    {
        return $this->hasMany(ServerCommands::class)->foreignKey('task_id');
    }

}