<?php

namespace Gini\ORM\Gapper\Agent\Group;

class User extends \Gini\ORM\Gapper\Agent\SObject
{
    public $group_id = 'bigint:20,comment:组id';
    public $user_id = 'bigint:20,comment:用户id';

    protected static $db_index = [
        'unique:group_id,user_id',
        'user_id'
    ];

}

