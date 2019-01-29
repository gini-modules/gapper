<?php
/**
* @file App.php
* @brief 各个节点自己管理APP，如果必要，可以把APP推送到gapper-server/gateway
*   但是，其实，云端对APP没有什么使用场景，节点APP纯粹因为节点需求存在
*   所以：先在节点本地添加、修改、删除 APP信息，然后采用某些机制，将节点数据在云端进行备份：节点写，云端同步、只读
* @author PiHiZi <pihizi@msn.com>
* @version 0.10
* @date 2019-01-28
 */

namespace Gini\ORM\Gapper\Agent;

class App extends \Gini\ORM\Gapper\Agent\SObject
{
    public $client_id = 'string:40';
    public $client_secret = 'string:40';

    public $name = 'string:40,comment:应用的模块名，这个名字应该固定跟代码库的模块名一致，是云端App的name和module_name的合并';
    public $title = 'string:120,comment:标题';
    public $short_title = 'string:20,comment:短标题';
    public $url = 'string:120,comment:访问地址';
    public $icon_url = 'string:250:comment:应用图标';
    public $type = 'string:40,comment:应用类型group/user/service';
    public $rate = 'int,default:0,comment:收费类型，这个在节点似乎没什么鸟用，暂时保留，我的设想是可以根据这个属性来判断用户或者是否可以在注册的时候直接安装该应用';
    public $font_icon = 'string:40,comment:某些地方需要代替大图标展示一个文本图标';

    protected static $db_index = [
        'unique:client_id', 
        'unique:name',
        'rate',
    ];

    const RATE_FREE = 0; // 免费应用
    const RATE_CHARGE = 1; // 付费应用

}

