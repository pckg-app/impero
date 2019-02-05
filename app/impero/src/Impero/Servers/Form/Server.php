<?php namespace Impero\Servers\Form;

use Pckg\Htmlbuilder\Element\Form\Bootstrap;
use Pckg\Htmlbuilder\Element\Form\ResolvesOnRequest;

class Server extends Bootstrap implements ResolvesOnRequest
{

    public function initFields()
    {
        $this->setAttribute('@submit.prevent', 'server.insert.call(server)');

        $this->addSelect('system_id')
             ->setAttribute('v-model', 'server.system_id')
             ->setLabel('OS')
             ->addOptions(['Ubuntu 16.04']);

        $this->addText('name')->setAttribute('v-model', 'server.name')->setLabel('Name');

        $this->addText('ip')->setAttribute('v-model', 'server.ip')->setLabel('IP');

        $this->addText('ptr')->setAttribute('v-model', 'server.ptr')->setLabel('PTR');

        $this->addSubmit();

        return $this;
    }

}