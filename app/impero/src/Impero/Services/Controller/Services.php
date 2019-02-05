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
        return '<impero-service-install-' . $service->service . ' :server="' . htmlspecialchars(json_encode($server)) .
            '" :service="' . htmlspecialchars(json_encode($service)) . '"></impero-service-install-' .
            $service->service . '>';
    }

    public function postInstallAction(Server $server, Service $service)
    {
        return ['success' => true];
    }

}