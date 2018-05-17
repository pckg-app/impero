<?php namespace Impero\Services\Controller;

use Impero\Services\Entity\Services as ServicesEntity;

class Services
{

    public function getServicesAction()
    {
        return [
            'services' => (new ServicesEntity())->all(),
        ];
    }

}