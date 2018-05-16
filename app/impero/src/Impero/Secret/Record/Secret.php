<?php namespace Impero\Secret\Record;

use Impero\Secret\Entity\Secrets;
use Pckg\Database\Record;

class Secret extends Record
{

    protected $entity = Secrets::class;

    public function getArrayKeysAttribute()
    {
        return json_decode($this->keys, true);
    }

}