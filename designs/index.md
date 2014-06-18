---
title: Gapper Client
template: page.jade
date: 2014-6-12
---

功能说明
===

Gapper Client为Gapper的第三方APP提供用户登录和验证机制

#### 示例代码

##### 默认是强制需要用户登录的

    // 实例化gapper client
    $client = \Gini\IoC::construct('\Gini\Gapper\Client');

    // 获取当前登录用户在gapper server存储的信息
    $udata = $client->getUserInfo();
     
    // 获取当前用户所属的group在gapper server存储的信息
    // 用户可能属于多个组，此事需要用户在gapper server为当前app指定所使用的组信息
    $gdata = $client->getGroupInfo();
    
##### 也可以不用登录

    // 实例化gapper client
    $client = \Gini\IoC::construct('\Gini\Gapper\Client', false);

    $username = $client->username;

    if ($username) {
        // 获取当前登录用户在gapper server存储的信息
        $udata = $client->getUserInfo();

        // 获取当前用户所属的group在gapper server存储的信息
        // 用户可能属于多个组，此事需要用户在gapper server为当前app指定所使用的组信息
        $gdata = $client->getGroupInfo();
    }

