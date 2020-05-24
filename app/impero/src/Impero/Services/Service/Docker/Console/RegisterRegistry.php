<?php namespace Impero\Services\Service\Docker\Console;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Impero\Servers\Record\Server;
use Impero\Services\Service\Docker;
use Pckg\Framework\Console\Command;
use Pckg\Generic\Record\DataAttribute;
use Symfony\Component\Console\Input\InputOption;

class RegisterRegistry extends Command
{

    protected function configure()
    {
        $this->setName('service:docker:register-registry')
            ->setDescription('Register registry with auth');
    }

    public function handle()
    {
        $registry = $this->askQuestion('Enter registry');
        $user = $this->askQuestion('User');
        $pass = $this->askQuestion('Pass');

        $this->outputDated($registry);
        $this->outputDated($user);
        if (!$this->askConfirmation('Is correct?')) {
            return;
        }

        $key = Key::createNewRandomKey();
        $ascii = $key->saveToAsciiSafeString();

        DataAttribute::getAndUpdateOrCreate([
            'morph_id' => 'registry',
            'poly_id' => $registry,
            'slug' => 'auth',
        ], [
            'value' => json_encode([
                'user' => $user,
                'pass' => Crypto::encrypt($pass, $key),
                'key' => $ascii,
            ]),
        ]);
    }

}