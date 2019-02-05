<?php namespace Impero\Services\Service;

/**
 * Class S3cmd
 *
 * @package Impero\Services\Service
 */
class S3cmd extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 's3cmd';

    /**
     * @var string
     */
    protected $name = 'S3cmd';

    /**
     * @return mixed|null
     */
    public function getVersion()
    {
        return null;
    }

    public function put($file, $to)
    {
        /**
         * First we need to dump configuration.
         */
        $configFile = $this->dumpConfiguration();

        $bucket = 'comms';
        $command = 's3cmd put ' . $file . ' s3://' . $bucket . '/' . $to . ' -c ' . $configFile;
        $this->exec($command);

        /**
         * Delete configuration.
         */
        $this->getConnection()->deleteFile($configFile);
    }

    public function dumpConfiguration()
    {
        $accessKey = dotenv('DO_ACCESS_KEY');
        $secretKey = dotenv('DO_SECRET_KEY');
        $space = 'ams3';
        $hostBase = $space . '.digitaloceanspaces.com';
        $hostBucket = '%(bucket)s.' . $space . '.digitaloceanspaces.com';

        $config = '[default]
access_key = ' . $accessKey . '
access_token = 
add_encoding_exts = 
add_headers = 
bucket_location = US
ca_certs_file = 
cache_file = 
check_ssl_certificate = True
check_ssl_hostname = True
cloudfront_host = cloudfront.amazonaws.com
default_mime_type = binary/octet-stream
delay_updates = False
delete_after = False
delete_after_fetch = False
delete_removed = False
dry_run = False
enable_multipart = True
encoding = UTF-8
encrypt = False
expiry_date = 
expiry_days = 
expiry_prefix = 
follow_symlinks = False
force = False
get_continue = False
gpg_command = /usr/bin/gpg
gpg_decrypt = %(gpg_command)s -d --verbose --no-use-agent --batch --yes --passphrase-fd %(passphrase_fd)s -o %(output_file)s %(input_file)s
gpg_encrypt = %(gpg_command)s -c --verbose --no-use-agent --batch --yes --passphrase-fd %(passphrase_fd)s -o %(output_file)s %(input_file)s
gpg_passphrase = 
guess_mime_type = True
host_base = ' . $hostBase . '
host_bucket = ' . $hostBucket . '
human_readable_sizes = False
invalidate_default_index_on_cf = False
invalidate_default_index_root_on_cf = True
invalidate_on_cf = False
kms_key = 
limitrate = 0
list_md5 = False
log_target_prefix = 
long_listing = False
max_delete = -1
mime_type = 
multipart_chunk_size_mb = 15
multipart_max_chunks = 10000
preserve_attrs = True
progress_meter = True
proxy_host = 
proxy_port = 0
put_continue = False
recursive = False
recv_chunk = 65536
reduced_redundancy = False
requester_pays = False
restore_days = 1
secret_key = ' . $secretKey . '
send_chunk = 65536
server_side_encryption = False
signature_v2 = False
simpledb_host = sdb.amazonaws.com
skip_existing = False
socket_timeout = 300
stats = False
stop_on_error = False
storage_class = 
urlencoding_mode = normal
use_https = True
use_mime_magic = True
verbosity = WARNING
website_endpoint = http://%(bucket)s.s3-website-%(location)s.amazonaws.com/
website_error = 
website_index = index.html
';

        $file = '/home/impero/.s3cmdconf';
        $this->getConnection()->saveContent($file, $config);

        return $file;
    }

}