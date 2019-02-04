<?php namespace Impero\Servers\Controller;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Impero\Servers\Dataset\Servers as ServersDataset;
use Impero\Servers\Entity\Servers as ServersEntity;
use Impero\Servers\Entity\ServersDependencies;
use Impero\Servers\Entity\ServersServices;
use Impero\Servers\Entity\Systems;
use Impero\Servers\Form\Server as ServerForm;
use Impero\Servers\Record\Server;
use Impero\Services\Service\Connection\SshConnection;
use Pckg\Generic\Service\Generic;
use Pckg\Generic\Service\Generic\CallableAction;
use Throwable;

class Servers
{

    public function getIndexAction()
    {
        vueManager()->addView('Impero/Servers:servers/index.vue');

        return view('servers/index');
    }

    public function getServersAction(ServersDataset $serversDataset)
    {
        return [
            'servers' => $serversDataset->getServersForUser(),
        ];
    }

    public function getViewServerAction(ServersDataset $serversDataset)
    {
        return view('servers/one');
    }

    public function getServerAction(Server $server)
    {
        return [
            'server' => $server,
        ];
    }

    public function getServerServicesAction(ServersDataset $serversDataset, Server $server)
    {
        return [
            'services' => $serversDataset->getServerServices($server),
        ];
    }

    public function getServerDependenciesAction(ServersDataset $serversDataset, Server $server)
    {
        return [
            'dependencies' => $serversDataset->getServerDependencies($server),
        ];
    }

    public function getServerWebsitesAction(ServersDataset $serversDataset, Server $server)
    {
        return [
            'websites' => $serversDataset->getServerApplications($server),
        ];
    }

    public function getNetworkInterfacesAction(ServersDataset $serversDataset, Server $server)
    {
        return [
            'networkInterfaces' => $serversDataset->getServerNetworkInterfaces($server),
        ];
    }

    public function getFirewallSettingsAction(ServersDataset $serversDataset, Server $server)
    {
        return [
            'firewallSettings' => $serversDataset->getServerFirewallSettings($server),
        ];
    }

    public function getAddServerAction(ServerForm $serverForm, Generic $genericService)
    {
        vueManager()->addView('Impero/Servers:servers/add.vue', ['serverForm' => $serverForm]);

        $genericService->touchBlock('left')->addAction(new CallableAction(function() {
                return view('servers/add_sidebar');
            }));

        return view('servers/add');
    }

    public function postAddServerAction(Server $server, ServerForm $serverForm)
    {
        $serverForm->populateToRecord($server);

        return response()->respondWithSuccess();
    }

    public function getRefreshServersServiceStatusAction($serversService)
    {
        $serversService = (new ServersServices())->where('id', $serversService)->oneOrFail();

        $serversService->refreshStatus();

        $serversService->withStatus();

        return response()->respondWithSuccess(['serversService' => $serversService]);
    }

    public function getRefreshServersDependencyStatusAction($serversDependency)
    {
        $serversDependency = (new ServersDependencies())->where('id', $serversDependency)->oneOrFail();

        $serversDependency->refreshStatus();

        $serversDependency->withStatus();

        return response()->respondWithSuccess(['serversDependency' => $serversDependency]);
    }

    public function getRefreshServerJobsAction(Server $server)
    {
        $server->refreshJobs();

        return response()->respondWithSuccess(['jobs' => $server->jobs]);
    }

    public function getWebhookAction()
    {
        /**
         * Hardcoded, currently used for gnpdev.
         */
        //$server = (new \Impero\Servers\Entity\Servers())->where('id', 2)->one();
        //$server->getConnection()->exec('cd /www/gnpdev/gnpdev.gonparty.eu/htdocs/ && php console project:pull');

        return 'ok';
    }

    public function postWebhookAction()
    {
        return $this->getWebhookAction();
    }

    public function postInstallNewServerAction()
    {
        /**
         * Get encrypted password and decrypt it.
         */
        $encryptedPassword = post('password', null);
        $password = Crypto::decrypt($encryptedPassword, Key::loadFromAsciiSafeString(config('security.key')));
        $hostname = post('hostname');
        $ip = server('REMOTE_ADDR', null);
        $port = post('port', 22);
        $user = 'impero';
        //dd("pass", $password);

        /**
         * Create new server.
         */
        $server = (new ServersEntity())->where('ip', $ip)->oneOr(function() use ($hostname, $ip, $port, $user) {
            return Server::create([
                                      'system_id' => Systems::OS_UBUNTU_1604_LTS_X64,
                                      'status'    => 'new',
                                      'name'      => $hostname,
                                      'ip'        => $ip,
                                      'ptr'       => $hostname,
                                      'port'      => $port,
                                      'user'      => $user,
                                  ]);
        });

        /**
         * We will generate ssh key for local www-data user to connect to server with impero username.
         * This is done with seperate cronjob for security (./install-key.sh), we wait for details.
         */
        $privateKey = path('storage') . 'private' . path('ds') . 'keys' . path('ds') . 'id_rsa_' . $server->id;
        if (!is_file($privateKey)) {
            $output = $return_var = null;
            $command = 'ssh-keygen -b 4096 -t rsa -C \'www-data@impero.foobar.si\' -f ' . $privateKey . ' -N "" 2>&1';
            exec($command, $output, $return_var);
            //d("generated", $command, $output, $return_var);
            // openssl rand -base64 32
        }

        /**
         * Change permissions.
         */
        chown($privateKey, $user);
        chown($privateKey . '.pub', $user);
        chmod($privateKey, 0775);
        chmod($privateKey . '.pub', 0775);

        /**
         * Then we will transfer key to remote.
         * If this fails (firewall), notify user.
         */
        $output = $return_var = null;

        /*if ($server->status == 'new') {
            $command = 'sshpass -p ' . $password . ' ssh-copy-id -p ' . $port . ' -i ' . $privateKey . '.pub ' . $user .
                       '@' . $ip . ' 2>&1';
            $passfile = '/tmp/pass.tmp.' . sha1(microtime());
            file_put_contents($passfile, $password);

            $command = 'sshpass -f "' . $passfile . '" scp -r ' . $user . '@' . $hostname . ':/some/remote/path /some/local/path';
            exec($command, $output, $return_var);

            d("copied", $command, $output, $return_var);
        }*/

        $connection = null;
        try {
            /**
             * Connect with password.
             */
            $connection = new SshConnection($server, $ip, $user, $port, $password, 'password');
        } catch (Throwable $e) {
            die("wrong password, port not opened, user not created or copy error : " . exception($e));
        }

        try {
            /**
             * Copy public identity.
             */
            $connection->exec('mkdir /home/impero/.ssh/');
            $connection->exec('chown impero:impero /home/impero/.ssh');
            $connection->exec('chmod 700 /home/impero/.ssh');
            $connection->sftpSend($privateKey . '.pub', '/home/impero/.ssh/impero.key.pub');
            $connection->exec('cat /home/impero/.ssh/impero.key.pub >> /home/impero/.ssh/authorized_keys');
            $connection->exec('rm /home/impero/.ssh/impero.key.pub');
            $connection->exec('chmod 600 /home/impero/.ssh/authorized_keys');
        } catch (Throwable $e) {
            die("error copying key : " . exception($e));
        }

        $connection->close();

        /**
         * Check if transfer was successful.
         * If successful, disable login with password and change ssh config
         * # PermitRootLogin no / without-password
         */
        try {
            $connection = new SshConnection($server, $ip, $user, $port, $privateKey);
            $connection->close();
        } catch (Throwable $e) {
            echo "Add keys manually:\n";
            echo "echo " . file_get_contents($privateKey . '.pub') . " >> /home/impero/.ssh/authorized_keys\n";

            echo "Add known hosts manually:\n";
            echo "ssh-keyscan -t rsa impero.foobar.si >> /home/impero/.ssh/known_hosts";

            die('error ' . exception($e));

            return response()->respondWithError([
                                                    'error' => exception($e),
                                                ]);
        }

        die('success');

        return response()->respondWithSuccess();
        /**
         * chmod -R g+w
         */
    }

    public function getInstallShAction()
    {
        /**
         * Generate password.
         */
        $password = auth()->createPassword(40);

        /**
         * Encrypt it for useradd action.
         */
        $allowed = "abcdefghiklmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ0123456789";
        $salt = substr($allowed, rand(0, strlen($allowed) - 1), 1) . substr($allowed, rand(0, strlen($allowed) - 1), 1);
        $cryptedPassword = crypt($password, $salt);

        /**
         * Encrypt it so we can decrypt it later and connect to server.
         */
        $secret = Crypto::encrypt($password, Key::loadFromAsciiSafeString(config('security.key')));

        return view('servers/install.sh', [
            'password'        => $password,
            'cryptedPassword' => $cryptedPassword,
            'secret'          => $secret,
            'userhash'        => sha1(1),
        ]);
    }

    public function postConnectAction(Server $server)
    {
        return response()->respondWithSuccess([
                                                  'connection' => !!$server->getConnection(),
                                              ]);
    }

    public function postCronjobAction(Server $server)
    {
        $server->addCronjob(post('command'));

        return ['success' => true];
    }

}
