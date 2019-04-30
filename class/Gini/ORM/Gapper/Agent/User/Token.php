<?php

namespace Gini\ORM\Gapper\Agent\User;

class Token extends \Gini\ORM\Gapper\Agent\SObject
{
    public $token = 'string:120';
    public $user_id = 'bigint:20,comment:用户id';
    public $client_id = 'string:40,comment:app的client_id';
    public $ctime = 'timestamp';    // 创建时间
    protected static $db_index = [
        'unique:token',
        'unique:user_id,client_id',
        'ctime'
    ];
}

