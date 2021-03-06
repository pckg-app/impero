<?php namespace Impero\Services\Service;

use Exception;
use Impero\Servers\Record\Server;
use Impero\Servers\Record\Task;
use Impero\Servers\Service\ConnectionManager;
use Impero\Services\Service\Connection\LocalConnection;
use Impero\Services\Service\Crypto\Crypto;

/**
 * Class GPG
 *
 * @package Impero\Services\Service
 */
class GPG extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'gpg';

    /**
     * @var string
     */
    protected $name = 'GPG';

    /**
     * @return mixed|string
     */
    public function getVersion()
    {
        return 'version todo';
    }

    /**
     * @param $input
     * @param $output
     *
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function decrypt(Crypto $crypto, $output = null)
    {
        if (!$output) {
            $output = $this->prepareDirectory('random') . sha1random();
        }
        $input = $crypto->getFile();
        $from = $crypto->getFrom();
        $to = $crypto->getTo();
        $keyFiles = $crypto->getKeys();

        /**
         * When we decrypt
         */

        /**
         * When decrypting replication backup we already hold private key.
         * When decrypting regular backup (backup restore) we decrypt on target server with private key from impero.
         */
        $command = 'gpg2 --output ' . $output . ' --decrypt ' . $input;
        if ($to) {
            $this->exec($command);

            return $output;
        }
        die("why is this used?");
        $toGpgService = new GPG($to);
        $toGpgService->tempUseFile($crypto->getKeys()['public'], $from,
            function() use ($toGpgService, $from, $input, $output, $keyFiles) {
                //$toGpgService->tempUsePrivateKey(
                //  $keyFiles, $from, function() use ($keyFiles, $input, $output) {

                /**
                 * Decrypt file.
                 */
                $command = 'gpg2 --output ' . $output . ' --decrypt ' . $input;
                $this->exec($command);
                //}
                //);
            });

        return $output;
    }

    /**
     * @param          $file
     * @param Server   $from
     * @param callable $call
     */
    public function tempUseFile($file, Server $from, callable $call)
    {
        /**
         * Copy file to target server.
         */
        $newLocation = $this->prepareDirectory('random', $from) . sha1random();
        $this->copyFileTo($file, $from, $newLocation);

        /**
         * Call inner things.
         */
        $call($newLocation);

        /**
         * Remove file from target server.
         */
        $from->getConnection()->deleteFile($newLocation);
    }

    /**
     * @param        $file
     * @param Server $to
     */
    public function copyFileTo($file, Server $to, $target = null)
    {
        /**
         * Copy public key from impero to $to.
         * Copy public key from $from to $to.
         */
        $connection = $this->getConnection();
        if ($connection instanceof LocalConnection) {
            $connection->exec('rsync -a ' . $file . ' impero@' . $to->ip . ':' . ($target ?? $file) . ' -e \'ssh -p ' .
                              $to->port . ' -i ' . path('private') . 'keys/id_rsa_' . $to->id . '\'');
        } else {
            /**
             * Remotes are synced.
             */
            $connection->exec('rsync -a ' . $file . ' impero@' . $to->ip . ':' . ($target ?? $file) . ' -e \'ssh -p ' .
                              $to->port . '\'');
        }
    }

    /**
     * @param Server      $from
     * @param Server|null $to
     * @param             $input
     * @param null        $output
     *
     * @return null|string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws Exception
     */
    public function encrypt(Crypto $crypto, $output = null)
    {
        $from = $crypto->getFrom();
        $to = $crypto->getTo();
        $input = $crypto->getFile();
        if (!$output) {
            $output = $this->prepareDirectory('random') . sha1random();
        }

        /**
         * When we encrypt for known server (replication) we generate public key and private key on target server.
         * When we encrypt things for unknown server (regular backups) we generate public and private key on /impero.
         * Generate key threesome.
         */
        $toConnection = $to
            ? $to->getConnection() : context()
                ->getOrCreate(ConnectionManager::class)
                ->createConnection();
        $toGpgService = (new GPG($toConnection));
        $keyFiles = $toGpgService->generateThreesome();
        $crypto->setKeys($keyFiles);

        /**
         * Public key generated and transfered to $from / source server so we can encrypt file with public key
         *  - known: $to / target -> source
         *  - unknown: impero -> source
         * We also import public key to gpg store, encrypt file, remove it from store and delete it from server.
         */
        $toGpgService->tempUseFile($keyFiles['public'], $from,
            function($tempFile) use ($toGpgService, $from, $keyFiles, $input, $output) {
                $toGpgService->tempUsePublicKey($tempFile, $keyFiles, $from,
                    function() use ($keyFiles, $input, $output) {

                        /**
                         * Encrypt file.
                         */
                        $command = 'gpg2 --output ' . $output . ' --trust-model always --encrypt --recipient ' .
                            $keyFiles['recipient'] . '@impero ' . $input;
                        $this->exec($command);
                    });
            });

        /**
         * Public key will not be used anymore, private key will be used only in case of decryption.
         *  - known target server (replication): leave it on target server
         *  - unknown target server (backup): leave it on impero
         */

        return $output;
    }

    /**
     * Generate private key, public key, revoke certificate.
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function generateThreesome()
    {
        /**
         * Check for keys dir. Create if nonexistent.
         */
        $destination = $this->getKeysDir();
        if (!$this->getConnection()->dirExists($destination)) {
            $this->getConnection()->createDir($destination, 0755, true);
        }

        /**
         * Generate paths.
         */
        $private = $destination . sha1random();
        $public = $destination . sha1random();
        $cert = $destination . sha1random();
        $recipient = sha1random();

        $this->generateKey($private, $public, $recipient, $cert);

        return [
            'private'   => $private,
            'public'    => $public,
            'cert'      => $cert,
            'recipient' => $recipient,
        ];
    }

    /**
     * @return string
     */
    public function getKeysDir()
    {
        $root = $this->getConnection() instanceof LocalConnection ? path('private') : '/home/impero/impero/';
        $dir = $root . 'service/random/';

        return $dir;
    }

    /**
     * @return null
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function generateKey($sec, $pub, $random, $cert)
    {
        $task = Task::create('Generating key threesome');

        return $task->make(function() use ($sec, $pub, $random, $cert) {
            /**
             * Set key config.
             */
            $keyLength = 4096;
            $keyLength = 2048;
            $keyBatch = '%echo Generating a basic OpenPGP key
     Key-Type: RSA
     Key-Length: ' . $keyLength . '
     Subkey-Type: ELG-E
     Subkey-Length: ' . $keyLength . '
     Name-Real: ' . $random . '
     Name-Comment: Impero Auto Key
     Name-Email: ' . $random . '@impero
     Expire-Date: 1y
     %no-ask-passphrase
     %no-protection
     #Passphrase: pass
     %pubring ' . $pub . '
     %secring ' . $sec . '
     # Do a commit here, so that we can later print "done" :-)
     %commit
     %echo done';

            /**
             * Create config file.
             */
            $filename = $this->getKeysDir() . sha1random();
            d('creating ' . $filename, get_class($this->getConnection()));
            $this->getConnection()->saveContent($filename, $keyBatch);

            /**
             * Generate key.
             */
            $command = 'gpg2 --batch --gen-key ' . $filename;
            $this->exec($command);

            /**
             * Import private key.
             */
            $this->importPrivateKey($pub);

            /**
             * Export private key if we need to export it.
             *
             * @T00D00
             */
            if ($this->getConnection() instanceof LocalConnection) {
                $this->exportPrivateKey($random . '@impero', $sec);
                $this->generateRevokeCertificate($random . '@impero', $cert);
            }
        });
    }

    /**
     * @param $file
     */
    public function importPrivateKey($file)
    {
        $command = 'gpg2 --allow-secret-key-import --import ' . $file;
        $this->exec($command);
    }

    /**
     * @param $name
     * @param $output
     *
     * @throws Exception
     */
    public function exportPrivateKey($name, $output = null)
    {
        if (!$output) {
            $output = $this->prepareDirectory('random') . sha1random();
        }

        $keys = $this->listKeys(2);
        $key = collect($keys)->first(function($key) use ($name) {
            return $key['receiver'] == $name;
        });

        if (!$key) {
            throw new Exception('Key doesnot exist');
        }

        $command = 'gpg2 --export-secret-keys -a ' . $key['hash'] . ' > ' . $output;
        $this->exec($command);
    }

    /**
     * @return array
     */
    public function listKeys($gpg = '')
    {
        $command = 'gpg' . $gpg . ' --fingerprint';
        $this->exec($command, $output);
        if (is_array($output)) {
            $output = implode("\n", $output);
        }

        $keys = [];
        $key = [];
        if (!$output) {
            return $keys;
        }
        foreach (explode("\n", $output) as $line) {
            if (strpos($line, 'sub') === 0) {
                $key['sub'] = $line;
                $keys[] = $key;
                $key = [];
                continue;
            }

            if (strpos($line, 'pub') === 0) {
                $key['pub'] = $line;
                continue;
            }

            if (strpos($line, 'uid') === 0) {
                $key['uid'] = $line;
                $key['receiver'] = substr($line, strrpos($line, ' ') + 2, -1);
                continue;
            }

            $hash = trim(str_replace(' ', '', $line));
            if ($equals = strpos($hash, '=')) {
                $hash = substr($hash, $equals + 1);
            }
            $key['hash'] = $hash;
        }

        return $keys;
    }

    /**
     * @param $key
     * @param $output
     */
    public function generateRevokeCertificate($key, $output)
    {
        /**
         * Generate revoke certificate.
         */
        $command = 'gpg2 --gen-revoke ' . $key . ' > ' . $output;
        $this->exec($command);
    }

    /**
     * @param          $keyFiles
     * @param Server   $from
     * @param callable $call
     *
     * @throws Exception
     */
    public function tempUsePublicKey($publicKey, $keyFiles, Server $from, callable $call)
    {
        /**
         * Import public key to gpg service.
         *
         * @T00D00 - see option --recipient-file
         *         - see option --hidden-recipient
         */
        $fromGpgService = (new GPG($from->getConnection()));
        $fromGpgService->importPublicKey($publicKey);

        /**
         * Call inner things.
         */
        $call();

        /**
         * Remove public key from gpg service.
         */
        $fromGpgService->deletePublicKey($keyFiles);
    }

    /**
     * @param $file
     */
    public function importPublicKey($file)
    {
        $command = 'gpg --import ' . $file;
        $this->exec($command);
    }

    /**
     * @param $hash
     */
    public function deletePublicKey($hash)
    {
        //$keys = $this->listKeys();
        return;
        $command = 'gpg2 --batch --yes --delete-keys ' . $hash;
        $this->exec($command);
    }

    /**
     * @param          $keyFiles
     * @param Server   $from
     * @param callable $call
     *
     * @throws Exception
     */
    public function tempUsePrivateKey($keyFiles, Server $from, callable $call)
    {
        /**
         * Import public key to gpg service.
         *
         * @T00D00 - see option --recipient-file
         *         - see option --hidden-recipient
         */
        $fromGpgService = (new GPG($from->getConnection()));
        $fromGpgService->importPrivateKey($keyFiles['private']);

        /**
         * Call inner things.
         */
        $call();

        /**
         * Remove public key from gpg service.
         */
        $fromGpgService->deletePrivateKey($keyFiles);
    }

    /**
     * @param $hash
     */
    public function deletePrivateKey($hash)
    {
        return;
        $command = 'gpg2 --batch --yes --delete-secret-keys ' . $hash;
        $this->exec($command);
    }

    /**
     * @param $hash
     */
    public function deleteKeys($hash)
    {
        $this->deletePrivateKey($hash);
        $this->deletePublicKey($hash);
    }

    /**
     * @param $name
     * @param $output
     */
    public function exportPublicKey($name, $output)
    {
        $command = 'gpg --export ' . $name . ' > ' . $output;
        $this->exec($command);
    }

}