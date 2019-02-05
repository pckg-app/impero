<?php namespace Impero\Mysql\Form;

use Pckg\Htmlbuilder\Element\Form;
use Pckg\Htmlbuilder\Element\Form\ResolvesOnRequest;

class User extends Form\Bootstrap implements ResolvesOnRequest
{

    public function initFields()
    {
        $this->addHidden('id');

        $this->addText('name')->setLabel('Name:');

        $this->addSubmit();

        return $this;
    }

}