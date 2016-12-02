<?php

namespace Gini\Module\Gapper\Client
{
    trait RPCTrait
    {
        private static $_RPC;
        private static function getRPC()
        {
            return \Gini\Gapper\Client::getRPC();
        }
    }
}
