<?php

namespace Gini\Module
{
    class Gapper 
    {
        public static function setup()
        {
            /*
            date_default_timezone_set(\Gini\Config::get('system.timezone') ?: 'Asia/Shanghai');

            _G('HEADCSS', '<GINI-HEADCSS>');
            _G('HEADJS', '<GINI-HEADJS>');

            if (isset($_GET['locale'])) {
                $_SESSION['locale'] = $_GET['locale'];
            }
            if ($_SESSION['locale']) {
                \Gini\Config::set('system.locale', $_SESSION['locale']);
            }
            \Gini\I18N::setup();
             */
        }
    }
    
    if (!trait_exists('\Gini\Module\RPCTrait')) {
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

    if (!trait_exists('\Gini\Module\LoggerTrait')) {
        trait LoggerTrait
        {
            private $traitVarMethod;
            private $traitVarIdent;
            private function log($method='')
            {
                $this->traitVarIdent = get_class($this);
                $this->traitVarMethod = $method;
                return $this;
            }
            private function debug($msg, array $context=[])
            {
                $ident = $this->traitVarIdent;
                $method = $this->traitVarMethod;
                $msg = $method ? "<{$method}> [DEBUG] {$msg}" : $msg;
                \Gini\Logger::of($ident)->debug($msg, $context);
            }
            private function info($msg, array $context=[])
            {
                $ident = $this->traitVarIdent;
                $method = $this->traitVarMethod;
                $msg = $method ? "<{$method}> [INFO] {$msg}" : $msg;
                \Gini\Logger::of($ident)->info($msg, $context);
            }
            private function warn($msg, array $context=[])
            {
                $ident = $this->traitVarIdent;
                $method = $this->traitVarMethod;
                $msg = $method ? "<{$method}> [WARN] {$msg}" : $msg;
                \Gini\Logger::of($ident)->warn($msg, $context);
            }
            private function error($msg, array $context=[])
            {
                $ident = $this->traitVarIdent;
                $method = $this->traitVarMethod;
                $msg = $method ? "<{$method}> [ERROR] {$msg}" : $msg;
                \Gini\Logger::of($ident)->error($msg, $context);
            }
        }
    }
}
