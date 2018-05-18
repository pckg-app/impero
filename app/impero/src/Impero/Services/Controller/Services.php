<?php namespace Impero\Services\Controller;

use Impero\Servers\Record\Server;
use Impero\Services\Entity\Services as ServicesEntity;
use Impero\Services\Record\Service;

class Services
{

    public function getServicesAction()
    {
        return [
            'services' => (new ServicesEntity())->all(),
        ];
    }

    public function getInstallAction(Server $server, Service $service)
    {
        return 'show install ' . $service->name . ' on ' . $server->name . ' form';
    }

    public function postInstallAction(Server $server, Service $service)
    {
        return ['success' => true];
    }

}