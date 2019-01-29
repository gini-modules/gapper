<?php

namespace Gini\ORM\Gapper\Agent;

abstract class SObject extends \Gini\ORM\Object
{
    // 将gapper-server的数据缓存到本地
    protected static $db_name = 'gapper-server-agent-db';

    // 因为gapper这个模块不只是labmai团队再用
    // 为了让其他团队再升级的时候在不做任何额外操作的情况下仍然能够通过orm update的操作
    // 重写了db方法
    // 目的是：在没有做有效配置的情况下，绕过Agent的所有ORM检测
    public function db()
    {
        if (!self::hasValidDBConfig()) return;
        return parent::db();
    }

    private static function hasValidDBConfig()
    {
        $config = \Gini\Config::get('database.'.self::$db_name);
        $username = $config['username'];
        // 如果配置了user，就认为dsn和password也配置了
        // 如果有人配置了user，但是没有对应的dsn和password，那就是这个人配置有问题了
        if (!$username) return false;
        return true;
    }
}
