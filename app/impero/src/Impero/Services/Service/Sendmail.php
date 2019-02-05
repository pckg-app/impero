<?php namespace Impero\Services\Service;

/**
 * Class Sendmail
 *
 * @package Impero\Services\Service
 */
class Sendmail extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'sendmail';

    /**
     * @var string
     */
    protected $name = 'Sendmail';

    /**
     * @return bool|mixed|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()->exec('sendmail -d0.4 -bv root');

        $start = strpos($response, 'Version ') + strlen('Version ');
        $end = strpos($response, "\n");
        $length = $end - $start;

        return substr($response, $start, $length);
    }

}