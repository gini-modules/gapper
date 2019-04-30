<?php

namespace Gini\ORM\Gapper\Agent\User;

class Token extends \Gini\ORM\Gapper\Agent\SObject
{
    public $token = 'string:120';
    public $username = 'string:120,comment:用户名';
    public $client_id = 'string:40,comment:app的client_id';
    public $ctime = 'timestamp';    // 创建时间
    protected static $db_index = [
        'unique:token',
        'unique:username,client_id',
        'ctime'
    ];
}

