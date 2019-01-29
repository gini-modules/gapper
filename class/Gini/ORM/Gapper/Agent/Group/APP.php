<?php

namespace Gini\ORM\Gapper\Agent\Group;

class APP extends \Gini\ORM\Gapper\Agent\SObject
{
    public $group_id = 'bigint:20,comment:组id';
    public $app_name = 'string:40,comment:应用的模块名，这个名字应该固定跟代码库的模块名一致，是云端App的name和module_name的合并';

    protected static $db_index = [
        'unique:group_id,app_name'
    ];

}

