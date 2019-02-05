<?php namespace Impero\Services\Service\Ssh\Form;

use Pckg\Htmlbuilder\Element\Form;

class ServerSettings extends Form implements Form\ResolvesOnRequest
{

    public function initFields()
    {
        $this->addInteger('settings[sshPort]')->required();

        $this->addInteger('settings[loginGraceTime]')->required();

        $this->addCheckbox('settings[permitRootLogin]');

        $this->addCheckbox('settings[passwordAuthentication]');

        return $this;
    }

}