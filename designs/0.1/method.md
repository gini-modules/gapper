---
template: page.jade
---

属性和方法
===

    $client = \Gini\IoC::construct('\Gini\Gapper\Client');

### 属性

* username: 获取当前登录的用户名，为gapper server的username，可能为空

        $client->username;


### 方法

* getUserInfo():

        $client->getUserInfo();

    * 空：用户没有登录返回空
    * array：从gapper server获取的当前登录用户的信息

* getGroupInfo(): 返回用户所属的某个特定gapper group的信息数组

        $client->getGroupInfo();

    * 空：用户没有登录返回空
    * array：从gapper server获取的当前登录用户所属的某个特定gapper group的信息。如果用户有多个组，则会跳转到gapper的group choose页面要求用户做出选择

