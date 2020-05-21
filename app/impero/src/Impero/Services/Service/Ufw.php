<?php namespace Impero\Services\Service;

use Impero\Servers\Record\Task;

/**
 * Class Ufw
 *
 * @package Impero\Services\Service
 */
class Ufw extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'ufw';

    /**
     * @var string
     */
    protected $name = 'UFW';

    /**
     * @return bool|string
     */
    public function getVersion()
    {
        $response = $this->getConnection()->exec('ufw version');

        $start = strpos($response, 'ufw ') + strlen('ufw ');
        $end = strpos($response, "\n");
        $length = $end - $start;

        return substr($response, $start, $length);
    }

    public function getFirewallSettings()
    {
        return Task::create('Getting firewall settings')->make(function () {
            $this->exec('sudo ufw status', $output);

            if (strpos($output, 'Status: active') === false) {
                return [];
            }

            $rules = explode("\n", $output);
            $rules = array_slice($rules, 4);

            return collect($rules)->filter(function ($rule) {
                return trim($rule);
            })->map(function ($rule) {
                $rule = preg_replace('!\s+!', ' ', $rule);
                $rule = str_replace(' (v6)', '(v6)', $rule);
                list($port, $type, $from) = explode(' ', $rule);

                return [
                    'port' => $port,
                    'rule' => $type,
                    'from' => $from,
                ];
            })->all();

            /*'1194/udp                   ALLOW       Anywhere
19999                      ALLOW       Anywhere
22222                      ALLOW       Anywhere
3306                       ALLOW       Anywhere
2049                       ALLOW       10.135.76.71
9000                       ALLOW       Anywhere
80 (v6)                    ALLOW       Anywhere (v6)';*/
        });
    }

    public function activate()
    {
        $commands = [
            'sudo apt-get install ufw -y',
            'ufw disable',
            'ufw default deny incoming',
            'ufw default allow outgoing',
            'ufw allow 22',
            'ufw allow 80',
            'ufw allow 443',
            // docker
            'sudo ufw allow 2376/tcp && sudo ufw allow 7946/udp && sudo ufw allow 7946/tcp && sudo ufw allow 2377/tcp && sudo ufw allow 4789/udp',
            'ufw enable',
        ];
        $connection = $this->getConnection();
        foreach ($commands as $command) {
            $connection->exec($command);
        }
    }

}