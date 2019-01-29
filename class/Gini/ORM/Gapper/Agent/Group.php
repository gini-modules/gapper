<?php

namespace Gini\ORM\Gapper\Agent;

class Group extends \Gini\ORM\Gapper\Agent\SObject
{
    public $name = 'string:120,comment:组标识';
    public $title = 'string:120,comment:组名';
    public $abbr = 'string:40,comment:组简称';
    public $creator = 'string:120,comment:创建人的username';
    public $icon = 'string:250,comment:组logo';
    public $stime = 'datetime,comment:同步时间';

    protected static $db_index = [
        'unique:name',
    ];

}

