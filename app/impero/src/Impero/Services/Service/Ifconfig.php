<?php namespace Impero\Services\Service;

use Impero\Servers\Record\Task;

/**
 * Class Ifconfig
 *
 * @package Impero\Services\Service
 */
class Ifconfig extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'ifconfig';

    /**
     * @var string
     */
    protected $name = 'Ifconfig';

    public function getNetworkInterfaces()
    {
        return Task::create('Getting network interfaces')->make(function() {
            $this->exec('ifconfig', $output);

            $interfaces = explode("\n\n", $output);

            return collect($interfaces)->filter(function($interface) {
                return trim($interface);
            })->map(function($interface) {
                $name = substr($interface, 0, strpos($interface, ' '));

                if (strpos($interface, 'inet addr:')) {
                    $ipv4Start = strpos($interface, 'inet addr:') + strlen('inet addr:');
                    $ipv4End = strpos($interface, ' ', $ipv4Start);
                    $ipv4 = substr($interface, $ipv4Start, $ipv4End - $ipv4Start);
                } else {
                    $ipv4Start = strpos($interface, 'inet ') + strlen('inet ');
                    $ipv4End = strpos($interface, ' ', $ipv4Start);
                    $ipv4 = substr($interface, $ipv4Start, $ipv4End - $ipv4Start);
                }

                if (strpos($interface, 'inet6 addr: ')) {
                    $ipv6Start = strpos($interface, 'inet6 addr: ') + strlen('inet6 addr: ');
                    $ipv6End = strpos($interface, ' ', $ipv6Start);
                    $ipv6 = substr($interface, $ipv6Start, $ipv6End - $ipv6Start);
                } else {
                    $ipv6Start = strpos($interface, 'inet6 ') + strlen('inet6 ');
                    $ipv6End = strpos($interface, ' ', $ipv6Start);
                    $ipv6 = substr($interface, $ipv6Start, $ipv6End - $ipv6Start);
                }

                if (strpos($interface, 'Mask:')) {
                    $maskStart = strpos($interface, 'Mask:') + strlen('Mask:');
                    $maskEnd = strpos($interface, "\n", $maskStart);
                    $mask = substr($interface, $maskStart, $maskEnd - $maskStart);
                } else {
                    $maskStart = strpos($interface, 'netmask ') + strlen('netmask ');
                    $maskEnd = strpos($interface, " ", $maskStart);
                    $mask = substr($interface, $maskStart, $maskEnd - $maskStart);
                }

                if (strpos($interface, 'RX bytes')) {
                    $downStart = strpos($interface, '(', strpos($interface, 'RX bytes'));
                    $downEnd = strpos($interface, ")", $downStart);
                    $down = substr($interface, $downStart + 1, $downEnd - $downStart - 1);
                } else {
                    $downStartPackets = strpos($interface, 'RX packets');
                    $downStart = strpos($interface, '(', strpos($interface, 'RX', $downStartPackets));
                    $downEnd = strpos($interface, ")", $downStart);
                    $down = substr($interface, $downStart + 1, $downEnd - $downStart - 1);
                }

                if (strpos($interface, 'TX bytes')) {
                    $upStart = strpos($interface, '(', strpos($interface, 'TX bytes'));
                    $upEnd = strpos($interface, ")", $upStart);
                    $up = substr($interface, $upStart + 1, $upEnd - $upStart - 1);
                } else {
                    $upStartPackets = strpos($interface, 'TX packets');
                    $upStart = strpos($interface, '(', strpos($interface, 'TX', $upStartPackets));
                    $upEnd = strpos($interface, ")", $upStart);
                    $up = substr($interface, $upStart + 1, $upEnd - $upStart - 1);
                }

                return [
                    'name'       => $name,
                    'ipv4'       => $ipv4,
                    'ipv6'       => $ipv6,
                    'mask'       => $mask,
                    'uploaded'   => $up,
                    'downloaded' => $down,
                    'interface'  => $interface,
                ];
            })->all();

            /*
              ubuntu 16.04
              eth0      Link encap:Ethernet  HWaddr 16:9e:01:a4:08:60
              inet addr:46.101.182.189  Bcast:46.101.191.255  Mask:255.255.192.0
              inet6 addr: 2a03:b0c0:3:d0::ba:1001/64 Scope:Global
              inet6 addr: fe80::149e:1ff:fea4:860/64 Scope:Link
              UP BROADCAST RUNNING MULTICAST  MTU:1500  Metric:1
              RX packets:25983951 errors:0 dropped:0 overruns:0 frame:0
              TX packets:23793936 errors:0 dropped:0 overruns:0 carrier:0
              collisions:0 txqueuelen:1000
              RX bytes:39575647694 (39.5 GB)  TX bytes:4456359716 (4.4 GB)

              ubuntu 18.04
              lo: flags=73<UP,LOOPBACK,RUNNING>  mtu 65536
              inet 127.0.0.1  netmask 255.0.0.0
              inet6 ::1  prefixlen 128  scopeid 0x10<host>
              loop  txqueuelen 1000  (Local Loopback)
              RX packets 303112  bytes 23495692 (23.4 MB)
              RX errors 0  dropped 0  overruns 0  frame 0
              TX packets 303112  bytes 23495692 (23.4 MB)
              TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0
            */
        });
    }

}