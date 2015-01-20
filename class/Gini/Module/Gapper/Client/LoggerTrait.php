<?php

namespace Gini\Module\Gapper\Client
{
    trait LoggerTrait
    {
        private $traitVarMethod;
        private $traitVarIdent;
        private function log($method = '')
        {
            $this->traitVarIdent = get_class($this);
            $this->traitVarMethod = $method;

            return $this;
        }
        private function debug($msg, array $context = [])
        {
            $ident = $this->traitVarIdent;
            $method = $this->traitVarMethod;
            $msg = $method ? "<{$method}> [DEBUG] {$msg}" : $msg;
            \Gini\Logger::of($ident)->debug($msg, $context);
        }
        private function info($msg, array $context = [])
        {
            $ident = $this->traitVarIdent;
            $method = $this->traitVarMethod;
            $msg = $method ? "<{$method}> [INFO] {$msg}" : $msg;
            \Gini\Logger::of($ident)->info($msg, $context);
        }
        private function warn($msg, array $context = [])
        {
            $ident = $this->traitVarIdent;
            $method = $this->traitVarMethod;
            $msg = $method ? "<{$method}> [WARN] {$msg}" : $msg;
            \Gini\Logger::of($ident)->warn($msg, $context);
        }
        private function error($msg, array $context = [])
        {
            $ident = $this->traitVarIdent;
            $method = $this->traitVarMethod;
            $msg = $method ? "<{$method}> [ERROR] {$msg}" : $msg;
            \Gini\Logger::of($ident)->error($msg, $context);
        }
    }
}
