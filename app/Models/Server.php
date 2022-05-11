<?php

namespace App\Models;

use App\Components\GitLab;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Server extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ip',
        'login',
        'password',
        'domains_path',
    ];

    public static function addKeys($server, $token)
    {
        // connect to server
        $originalConnectionTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 2);
        try {
            $connection = \ssh2_connect($server['ip'], 22);
        } catch (\Exception $e) {
            ini_set('default_socket_timeout', $originalConnectionTimeout);
            return false;
        }

        // auth to server
        try {
            \ssh2_auth_password($connection, $server['login'], $server['password']);
        } catch (\Exception $e) {
            return false;
        }
        //164.90.174.248	root	js3Js8sn

        $commands = 'ssh-keyscan '.$server['ip'].' >> ~/.ssh/known_hosts';
        exec($commands);

        // add ssh key
        $commands = 'ssh-keygen -t rsa -N \'\' -b 4096 -C "server'.$server['ip'].'@ex.com" -f ~/.ssh/id_rsa <<< y';
        \ssh2_exec($connection, $commands);

        $stream = \ssh2_exec($connection, 'ssh-keyscan gitlab.com >> ~/.ssh/known_hosts');
        stream_set_blocking($stream, true);
        $streamOut = \ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
        stream_get_contents($streamOut);

        // get shh key
        $commands = 'cat ~/.ssh/id_rsa.pub';
        $stream = \ssh2_exec($connection, $commands);
        stream_set_blocking($stream, true);
        $streamOut = \ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
        $key = stream_get_contents($streamOut);
        $key = str_replace("\n", "", $key);

        GitLab::addSSHToUser($server['ip'], $key, $token);

        return true;
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }
}
