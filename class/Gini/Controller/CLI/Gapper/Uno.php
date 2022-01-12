<?php

namespace Gini\Controller\CLI\Gapper;

class Uno extends \Gini\Controller\CLI
{
    public function actionRegister()
    {
        $server  = \Gini\Config::get(('uno.server');
        $appConfig  = \Gini\Config::get(('uno.application');

        if (!$token) {
            die('您似乎没有正确的配置token!');
        }

        if (!$appConfig['client_id'] || !$appConfig['client_secret']) {
            die('您似乎没有正确的配置clientId和clientSecret!');
        }

        $request_url = $server['url'] . "app/{$appConfig['client_id']}";

        $entries = [];
        $entriesConfig = \Gini\Config::get(('uno.entries');
        $entriesUrl = \Gini\Config::get(('uno.entries_url');
        foreach ($entriesConfig as $ek => $ev) {
            $entries[$ek] = [
                'title' => $ev['title'],
                'uri' => $entriesUrl.$ek,
            ];
        }

        $data = [
            'client_secret' => $appConfig['client_secret'],
            'name' => $appConfig['name'],
            'short_name' => $appConfig['shortName'],
            'description' => '',
            'icon' => '',
            'url' => $appConfig['url'],
            'active' => true,
            'show' => true,
            'type' => 'app',
            'api' => [
                'logout' => '',
                'entries' => $entries,
            ],
        ];

        try {
            $rest = new \Gini\HTTP();
            $rest->header('X-Gapper-OAuth-Token', $token);
            $response = $rest->put($request_url, $data);
            $result = @json_decode($response, true);
            
            if ($result['id']) {
                echo "Done";
            } else {
                die('无法更新应用');
            }
        } catch (Exception $e) {
            die("无法更新应用");
        }
    }
}
