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
                    $rpc = \Gini\IoC::construct('\Gini\RPC', $api, 'gapper');
                    $bool = $rpc->app->authorize($client_id, $client_secret);
                    if (!$bool) {
                        throw new \Exception('Your app was not registered in gapper server!');
                    }
                    self::$_RPC = $rpc;
                } catch (\Gini\RPC\Exception $e) {
                    \Gini\Logger::of('gapper')->error('Gapper::getRPC error: {message}', ['message' => $e->getMessage()]);
                }
            }

            return self::$_RPC;
        }
    }
}
