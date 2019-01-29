<?php

namespace Gini\ORM\Gapper\Agent\APP;

class Group extends \Gini\ORM\Gapper\Agent\SObject
{
    public $app_name = 'string:40,comment:应用的模块名，这个名字应该固定跟代码库的模块名一致，是云端App的name和module_name的合并';
    public $group_id = 'bigint:20,comment:组id';
    public $ctime = 'datetime,comment:本地的关联关系创建时间';

    protected static $db_index = [
        'unique:group_id,app_name',
        'ctime',
    ];
}
