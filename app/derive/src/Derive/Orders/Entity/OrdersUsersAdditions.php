<?php namespace Derive\Orders\Entity;

use Pckg\Database\Entity;
use Pckg\Database\Repository;

class OrdersUsersAdditions extends Entity
{

    protected $repositoryName = Repository::class . '.gnp';

    public function recalculateZoi()
    {
        
    }

}