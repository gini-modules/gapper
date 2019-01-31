<?php

namespace Gini\ORM\Gapper\Agent\User;

class Identity extends \Gini\ORM\Gapper\Agent\SObject
{
    public $source = 'string:120';
    public $identity = 'string:120';
    public $user_id = 'bigint:20,comment:用户id';

    protected static $db_index = [
        'unique:identity,source',
        'user_id,source'
    ];

}

