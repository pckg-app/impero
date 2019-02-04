<?php namespace Impero\Apache\Form;

use Pckg\Htmlbuilder\Element\Form;
use Pckg\Htmlbuilder\Element\Form\ResolvesOnRequest;

class Site extends Form\Bootstrap implements ResolvesOnRequest
{

    public function initFields()
    {
        $this->addHidden('id');

        $this->addText('server_name')->setLabel('Server name (URL)')->setPrefix('http(s)://');

        $this->addText('server_alias')->setLabel('Server aliases:')->setHelp('<p>Separated by spaces</p>');

        $this->addText('document_root')
             ->setLabel('Document root:')
             ->setPrefix('/www/{username}/')
             ->setHelp('<p>Directory will be automatically created, if non existent.</p>')
             ->setSuffix('/htdocs/');

        $this->addSelect('ssl')
             ->setLabel('SSL')
             ->setPlaceholder('Select SSL method')
             ->setHelp('<p>By default, we disabled SSL.</p><p>You can enable it by uploading cerficates or choosing LetsEncrypt FREE alternative.</p>')
             ->addOptions([
                              ''            => 'Disabled (default)',
                              'file'        => 'File',
                              'letsencrypt' => 'LetsEncrypt',
                          ]);
        $this->addSslFileFields();
        $this->addSslLetsencrypt();
        $this->addLogFields();

        $this->addCheckbox('enabled')->setLabel('Site is enabled');

        $this->addSubmit();

        return $this;
    }

    private function addSslFileFields()
    {
        $this->addFile('ssl_certificate_key')->setLabel('*.crt')/*
            ->extensions(['crt'])
            ->requiredWhen('ssl', 'file')
            ->enabledWhen('ssl', 'file')*/
        ;

        $this->addFile('ssl_certificate_key_file')->setLabel('*.key')/*
            ->extensions(['key'])
            ->requiredWhen('ssl', 'file')
            ->enabledWhen('ssl', 'file')*/
        ;
    }

    private function addSslLetsencrypt()
    {
        $this->addCheckbox('ssl_letsencrypt_autorenew')->setLabel('Automatically renew certificate');
    }

    private function addLogFields()
    {
        $this->addCheckbox('error_log')
             ->setHelp('<p>Check this option if you want to log errors.</p>')
             ->setLabel('Error log');

        $this->addCheckbox('access_log')
             ->setHelp('<p>Check this option if you want to log access to site.</p>')
             ->setLabel('Access log');
    }

}