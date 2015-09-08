<?php

namespace Gini\Module\Gapper\Client
{
    trait RPCTrait
    {
        private static $_RPC;
        private static function getRPC()
        {
            if (!self::$_RPC) {
                try {
                    $config = (array) \Gini\Config::get('gapper.rpc');
                    $api = $config['url'];
                    $client_id = $config['client_id'];
                    $client_secret = $config['client_secret'];
                    self::$_RPC = $rpc = \Gini\IoC::construct('\Gini\RPC', $api);
                    $token = $rpc->gapper->app->authorize($client_id, $client_secret);
                    if (!$token) {
                        \Gini\Logger::of('gapper')->error('Your app was not registered in gapper server!');
                    }
                } catch (\Gini\RPC\Exception $e) {
                    \Gini\Logger::of('gapper')->error('Gapper::getRPC {message}[{code}]', ['code' => $e->getCode(), 'message' => $e->getMessage()]);
                }
            }

            return self::$_RPC;
        }
    }
}
