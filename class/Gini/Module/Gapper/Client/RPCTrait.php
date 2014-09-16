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
                    $api = \Gini\Config::get($type . '.url');
                    $client_id = \Gini\Config::get($type . '.client_id');
                    $client_secret = \Gini\Config::get($type . '.client_secret');
                    $rpc = \Gini\IoC::construct('\Gini\RPC', $api, $type);
                    $bool = $rpc->authorize($client_id, $client_secret);
                    if (!$bool) {
                        throw new \Exception('Your APP was not registered in gapper server!');
                    }
                    self::$_RPCs[$type] = $rpc;
                } catch (\Gini\RPC\Exception $e) {
                }
            }
            return self::$_RPCs[$type];
        }
    }
}

