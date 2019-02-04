<?php namespace Impero\Servers\Dataset;

use Impero\Servers\Entity\Servers as ServersEntity;
use Impero\Servers\Record\Server;
use Impero\Services\Service\Apache;
use Impero\Services\Service\Connection\SshConnection;
use Impero\Services\Service\Cron;
use Impero\Services\Service\GPG;
use Impero\Services\Service\Mysql;
use Impero\Services\Service\Nginx;
use Impero\Services\Service\OpenSSL;
use Impero\Services\Service\Openvpn;
use Impero\Services\Service\Php;
use Impero\Services\Service\PhpFpm;
use Impero\Services\Service\Pureftpd;
use Impero\Services\Service\Sendmail;
use Impero\Services\Service\Ssh;
use Impero\Services\Service\Ufw;
use Impero\Services\Service\Zip;
use Pckg\Database\Relation\HasAndBelongsTo;

class Servers
{

    public function getServerForUser($serverId)
    {
        return (new ServersEntity())->withTags()->withSystem()->withServices(function(HasAndBelongsTo $services) {
                $services->getMiddleEntity()->withStatus();
            })->withDependencies(function(HasAndBelongsTo $dependencies) {
                $dependencies->getMiddleEntity()->withStatus();
            })->withJobs()->where('id', $serverId)->oneOrFail();
    }

    public function getServersForUser()
    {
        return (new ServersEntity())->withTags()->withSystem()->withServices(function(HasAndBelongsTo $services) {
                $services->getMiddleEntity()->withStatus();
            })->withDependencies(function(HasAndBelongsTo $dependencies) {
                $dependencies->getMiddleEntity()->withStatus();
            })->withJobs()->all()->map(function(Server $server) {
                $data = $server->toArray();
                try {
                    $connection = $server->getConnection();
                } catch (\Throwable $e) {
                    $data['status'] = $e->getMessage();
                }

                $data['tags'] = $server->tags->toArray();
                $data['os'] = $server->system->toArray();

                $data['url'] = url('impero.servers.server', ['server' => $server->id]);
                $data['applications'] = $this->getServerApplications();
                $data['websites'] = $this->getServerWebsites();
                $data['deployments'] = $this->getServerDeployments();
                $data['logs'] = $this->getServerLogs();

                return $data;
            });
    }

    public function getServerServices()
    {
        $connection = new SshConnection();

        $services = [
            new Apache($connection),
            new Mysql($connection),
            new Ufw($connection),
            new Php($connection),
            new PhpFpm($connection),
            new Nginx($connection),
            new Ssh($connection),
            new Cron($connection),
            new Openvpn($connection),
            new Sendmail($connection),
            new Pureftpd($connection),
            new OpenSSL($connection),
            new Zip($connection),
            new GPG($connection),
        ];

        $data = [];
        foreach ($services as $service) {
            $data[] = [
                'name'      => $service->getName(),
                'version'   => $service->getVersion(),
                'status'    => $service->getStatus(),
                'installed' => $service->isInstalled() ? 'yes' : 'no',
            ];
        }

        return $data;
    }

    public function getServerDependencies()
    {
        return [
            [
                'name'    => 'Composer',
                'version' => $this->getVersion(),
            ],
            [
                'name'    => 'Npm',
                'version' => $this->getVersion(),
            ],
            [
                'name'    => 'Bower',
                'version' => $this->getVersion(),
            ],
            [
                'name'    => 'Git',
                'version' => $this->getVersion(),
            ],
            [
                'name'    => 'Svn',
                'version' => $this->getVersion(),
            ],
            [
                'name'    => 'Yarn',
                'version' => $this->getVersion(),
            ],
        ];
    }

    public function getServerDeployments()
    {
        return [
            [
                'application' => $this->getApplication(),
                'started_at'  => $this->getDatetime(),
                'ended_at'    => $this->getDatetime(),
                'status'      => 'ok',
                'log'         => '',
                'version'     => $this->getGitVersion(),
            ],
            [
                'application' => $this->getApplication(),
                'started_at'  => $this->getDatetime(),
                'ended_at'    => $this->getDatetime(),
                'status'      => 'ok',
                'log'         => '',
                'version'     => $this->getGitVersion(),
            ],
            [
                'application' => $this->getApplication(),
                'started_at'  => $this->getDatetime(),
                'ended_at'    => $this->getDatetime(),
                'status'      => 'ok',
                'log'         => '',
                'version'     => $this->getGitVersion(),
            ],
        ];
    }

    public function getApplication()
    {
        $applications = $this->getServerApplications();

        return $applications[array_rand($applications)];
    }

    public function getServerApplications()
    {
        return [
            [
                'name'    => 'GoNParty Shop',
                'url'     => 'http://gonparty.eu',
                'status'  => 'success',
                'version' => $this->getGitVersion(),
                'type'    => 'www',
                'source'  => 'git',
            ],
            [
                'name'    => 'HardIsland Shop',
                'url'     => 'http://shop.hardisland.com',
                'status'  => 'warning',
                'version' => $this->getGitVersion(),
                'type'    => 'www',
                'source'  => 'svn',
            ],
            [
                'name'    => 'GoNParty Market Place',
                'url'     => 'http://gonparty.xyz',
                'status'  => 'success',
                'version' => $this->getGitVersion(),
                'type'    => 'www',
                'source'  => 'zip',
            ],
            [
                'name'    => 'Start Maestro',
                'url'     => 'http://startmaestro.com',
                'status'  => 'danger',
                'version' => $this->getGitVersion(),
                'type'    => 'www',
                'source'  => 'git',
            ],
        ];
    }

    public function getServerWebsites()
    {
        return [
            [
                'name'        => 'GoNParty',
                'url'         => 'https://gonparty.eu',
                'application' => $this->getApplication(),
                'https'       => 'on',
                'version'     => $this->getGitVersion(),
                'status'      => 'offline',
                'urls'        => [
                    'si.gonparty.eu',
                    'www.gonparty.eu',
                    'hr.gonparty.eu',
                ],
            ],
            [
                'name'        => 'HardIsland',
                'url'         => 'https://shop.hardisland.com',
                'application' => $this->getApplication(),
                'https'       => 'on',
                'version'     => $this->getGitVersion(),
                'status'      => 'online',
                'urls'        => [],
            ],
            [
                'name'        => 'Server status',
                'url'         => 'http://status.foobar.si',
                'application' => $this->getApplication(),
                'https'       => 'on',
                'version'     => $this->getGitVersion(),
                'status'      => 'online',
                'urls'        => [],
            ],
            [
                'name'        => 'GNP.si',
                'url'         => 'http://gnp.si',
                'application' => $this->getApplication(),
                'https'       => 'on',
                'version'     => $this->getGitVersion(),
                'status'      => 'online',
            ],
        ];
    }

    private function getCommand()
    {
        $dirs = [
            '/backup/cli/',
            '/www/user/domain.tld/htdocs/',
            '/home/user/',
        ];
        $prefixes = ['sh', 'php'];
        $execs = ['console', 'database.sh', 'foo'];
        $args = [
            '--option --argument=1',
            '--bla --blabla --foo=bar',
        ];

        return $prefixes[array_rand($prefixes)] . ' ' . $dirs[array_rand($dirs)] . ' ' . $execs[array_rand($execs)] .
            ' ' . $args[array_rand($args)] . ' ';
    }

    private function getGitVersion()
    {
        return '#' . substr(sha1(microtime()), 0, 12);
    }

    private function getVersion()
    {
        $length = rand(1, 3);
        $versions = [];
        foreach (range(1, $length) as $length) {
            $versions[] = rand(1, 5);
        }

        return 'v' . implode('.', $versions);
    }

    private function getDatetime()
    {
        return date('Y-m-d H:i:s', rand(time() - (2 * 365 * 24 * 60 * 60), time()));
    }

    private function getServerLogs()
    {
        return [
            [
                'name'       => 'SSH key created',
                'created_at' => $this->getDatetime(),
            ],
        ];
    }

    private function getFrequency()
    {
        $freqs = [
            'every ' . rand(1, 50) . ' minutes',
            'every ' . rand(1, 50) . ' hours',
            'every day at ' . rand(0, 23) . ':00',
            'every monday and thursday at ' . rand(0, 23) . ':30',
            'every first friday in month',
            'every day',
        ];

        return $freqs[array_rand($freqs)];
    }

    private function getServerTags()
    {
        return ['loadbalancer', 'master', 'slave', 'mail', 'web', 'database', 'storage'];
    }

}