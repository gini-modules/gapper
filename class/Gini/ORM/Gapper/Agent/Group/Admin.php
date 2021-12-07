<?php

namespace Gini\ORM\Gapper\Agent\Group;

class Admin extends \Gini\ORM\Gapper\Agent\SObject
{
    public $group_id = 'bigint:20,comment:组id';

    protected static $db_index = [
        'unique:group_id',
    ];

}
