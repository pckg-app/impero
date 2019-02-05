<?php namespace Impero\Dependencies\Dependency;

use Impero\Services\Service\Php;

class Composer extends AbstractDependency
{

    protected $dependency = 'composer';

    protected $dependencies = [
        Php::class,
    ];

    public function getVersion()
    {
        $response = $this->getConnection()->exec('composer --version');

        $versionStart = strpos($response, 'Composer version ') + strlen('Composer version ');
        $versionEnd = strpos($response, ' ', $versionStart);
        $versionLength = $versionEnd - $versionStart;

        return substr($response, $versionStart, $versionLength);
    }

    public function getStatus()
    {
        $response = $this->getConnection()->exec('composer diagnose');

        $outdated = strpos($response, 'You are not running the latest stable version');

        return $outdated ? 'outdated' : 'ok';
    }

    public function install()
    {
        /**
         * @T00D00 - how will we handle hash changes?
         */
        $commands = [
            'php -r "copy(\'https://getcomposer.org/installer\', \'composer-setup.php\');"',
            'php -r "if (hash_file(\'SHA384\', \'composer-setup.php\') === \'544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061\') { echo \'Installer verified\'; } else { echo \'Installer corrupt\'; unlink(\'composer-setup.php\'); } echo PHP_EOL;"',
            'php composer-setup.php',
            'php -r "unlink(\'composer-setup.php\');"',
            'mv composer.phar /usr/local/bin/composer',
        ];
        $connection = $this->getConnection();
        foreach ($commands as $command) {
            $connection->exec($command);
        }
    }
}