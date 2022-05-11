<?php


namespace App\Components;


use Illuminate\Support\Facades\Log;

class GitLab
{
    const BASE_LINK = 'https://gitlab.com/api/v4';

    public static function createProject($nameProject, $token)
    {
        $dataGit = [
            'name' => $nameProject,
        ];

        $ch = curl_init(self::BASE_LINK . '/projects');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataGit));

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'PRIVATE-TOKEN: ' . $token
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result);
    }

    public static function addSSHToUser($title, $key, $token)
    {
        $dataGit = [
            'title' => $title,
            'key' => $key,
        ];

        $ch = curl_init(self::BASE_LINK . '/user/keys');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataGit));

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'PRIVATE-TOKEN: ' . $token
        ]);

        $data = curl_exec($ch);

        curl_close($ch);
    }
}
