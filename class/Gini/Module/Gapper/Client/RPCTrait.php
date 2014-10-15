<?php

namespace Gini\Module\Gapper\Client
{
    trait RPCTrait
    {
        private static $_RPCs = [];
        private static function getRPC($type='gapper')
        {
            if (!self::$_RPCs[$type]) {
                try {
                    $config = (array)\Gini\Config::get($type . '.rpc');
                    $api = $config['url'];
                    $client_id = $config['client_id'];
                    $client_secret = $config['client_secret'];
                    $rpc = \Gini\IoC::construct('\Gini\RPC', $api, $type);
                    $bool = $rpc->authorize($client_id, $client_secret);
                    if (!$bool) {
                        throw new \Exception('Your app was not registered in gapper server!');
                    }
                    self::$_RPCs[$type] = $rpc;
                } catch (\Gini\RPC\Exception $e) {
                    \Gini\Logger::of('gapper')->error('Gapper::getRPC error: {message}', ['message' => $e->getMessage()]);
                }
            }
            return self::$_RPCs[$type];
        }
    }
}

