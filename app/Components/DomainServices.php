<?php
namespace App\Components;

class DomainServices
{
    public static function createDomainTINY($domain)
    {

        $host = env('TINY_HOST', '');
        $port = env('TINY_PORT', '');
        $login = env('TINY_LOGIN', '');
        $password = env('TINY_PASSWORD', '');

        if(empty($host)) {
            return false;
        }

        $tinyConn = new TinyCPConnectorFull($host, $port);

        $authTiny = $tinyConn->Auth($login, $password);

        if($authTiny){
            $tinyConn->web___http___domain_add($domain);
        }
    }

    public static function createDomainDO($domain, $server)
    {
        $token = env('DO_TOKEN', '');

        $url = 'https://api.digitalocean.com/v2/domains';

        $data = [
            'name' => $domain,
            'ip_address' => $server
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}

