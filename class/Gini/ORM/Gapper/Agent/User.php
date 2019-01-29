<?php

namespace Gini\ORM\Gapper\Agent;

class User extends \Gini\ORM\Gapper\Agent\SObject
{
    public $name = 'string:120,comment:用户姓名';
    public $initials = 'string:10,comment:用户姓名缩写';
    public $username = 'string:120,comment:用户名';
    public $email = 'string:120,comment:email';
    public $phone = 'string:120,comment:电话';
    public $icon = 'string:250,comment:用户头像';
    public $stime = 'datetime,comment:同步时间';

    protected static $db_index = [
        'unique:username',
        'stime',
    ];

}

