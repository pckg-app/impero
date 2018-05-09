<?php namespace Impero\Secret\Entity;

use Impero\Secret\Record\Secret;
use Pckg\Database\Entity;

class Secrets extends Entity
{

    protected $record = Secret::class;

}