<?php

namespace Gini\ORM\Gapper\Agent;

class Auth extends \Gini\ORM\Gapper\Agent\SObject
{
    public $username = 'string:80,comment:用户名';
    public $password = 'string:100,comment:密码';
    protected static $db_index = [
        'unique:username'
    ];

}

