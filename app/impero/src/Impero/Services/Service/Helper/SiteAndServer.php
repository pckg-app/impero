<?php namespace Impero\Services\Service\Helper;

use Exception;
use Impero\Apache\Record\Site;
use Impero\Servers\Record\Server;
use Pckg\Queue\Service\RabbitMQ;
use Symfony\Component\Console\Input\InputOption;

trait SiteAndServer
{

    /**
     * @var Site
     */
    protected $site;

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var array
     */
    protected $config;

    /**
     * @return $this
     */
    public function addSiteAndServerOptions()
    {
        $this->addOptions([
                              'site'   => 'Site ID',
                              'server' => 'Server ID',
                              'config' => 'JSON config',
                              'shout'  => 'Shout event and channel',
                          ], InputOption::VALUE_REQUIRED);

        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function requireOptions()
    {
        $site = $this->option('site');
        $server = $this->option('server');
        $config = $this->decodeOption('config', true);

        if (!$site || !$server) {
            throw new Exception('Site and server are required');
        }

        return [$site, $server, $config];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getSiteAndServerOptions()
    {
        list($site, $server, $config) = $this->requireOptions();

        $this->outputDated('Fetching site and server');
        $site = Site::getOrFail($site);
        $server = Server::getOrFail($server);

        return [$site, $server, $config];
    }

    /**
     * @throws Exception
     */
    public function storeOptions()
    {
        list($site, $server, $config) = $this->getSiteAndServerOptions();

        $this->site = $site;
        $this->server = $server;
        $this->config = $config;
    }

    /**
     * @return mixed
     */
    public function getSitePckgConfig()
    {
        return $this->decodeOption('config', true);
    }

    /**
     * @return RabbitMQ
     */
    public function getRabbitMQ()
    {
        return resolve(RabbitMQ::class);
    }

    /**
     * Notify listener, if any, that we've finished with execution.
     */
    public function emitFinalEvent()
    {
        $this->emitEvent('ready');
    }

    /**
     * Notify listener, if any, that execution failed.
     */
    public function emitErrorEvent()
    {
        $this->emitEvent('error');
    }

    public function emitEvent($state)
    {
        $config = $this->decodeOption('shout', true);
        if (!$config) {
            $this->outputDated('No channel: event state ' . $state);
            return;
        }
        $this->outputDated('Emiting event state ' . $state);
        $broker = $this->getRabbitMQ();
        $broker->makeShoutExchange($config['channel']);
        $broker->shout($config['channel'], ['event' => $config['event'] . ':' . $state, 'state' => $state]);
    }

}