<?php

/**
 * @file Client.php
 * @brief APP <--> Gapper Client <--> Gapper Server
 *
 * @author Hongjie Zhu
 *
 * @version 0.1.0
 * @date 2014-06-18
 */

/**
 * $client = \Gini\IoC::construct('\Gini\Gapper\Client', true); (default)
 * $client = \Gini\IoC::construct('\Gini\Gapper\Client', false);
 * $username = $client->getCurrentUserName();
 * $userdata = $client->getUserInfo();
 * $groupdata = $client->getGroupInfo();.
 */
namespace Gini\Gapper;

class Client
{
    private static $_has_server_agent = null;
    public static function hasServerAgent()
    {
        if (!is_null(self::$_has_server_agent)) return self::$_has_server_agent;

        $config = \Gini\Config::get('database.gapper-server-agent-db');
        $username = $config['username'];
        // 如果配置了user，就认为dsn和password也配置了
        // 如果有人配置了user，但是没有对应的dsn和password，那就是这个人配置有问题了
        if (!$username) {
            self::$_has_server_agent = false;
        } else {
            self::$_has_server_agent = true;
        }
        return self::$_has_server_agent;
    }

    private static $_RPC;
    public static function getRPC()
    {
        if (self::$_RPC) return self::$_RPC;

        $config = (array) \Gini\Config::get('gapper.rpc');
        $api = $config['url'];
        $client_id = $config['client_id'];
        $client_secret = $config['client_secret'];
        $cacheKey = "app#client#{$client_id}#session_id";
        $token = self::cache($cacheKey);
        $rpc = \Gini\IoC::construct('\Gini\RPC', $api);
        if ($token) {
            $rpc->setHeader(['X-Gini-Session' => $token]);
        } else {
            $token = $rpc->gapper->app->authorize($client_id, $client_secret);
            if (!$token) {
                \Gini\Logger::of('gapper')->error('Your app was not registered in gapper server!');
            } else {
                self::cache($cacheKey, $token, 720);
                self::$_RPC = $rpc;
            }
        }

        return $rpc;
    }

    const STEP_LOGIN = 0;
    const STEP_GROUP = 1;
    const STEP_DONE = 2;
    const STEP_USER_401 = 3;
    const STEP_GROUP_401 = 4;

    private static $sessionKey = 'gapper.client';
    private static $sessionAuthPrefix = 'gapper-auth-';
    private static function prepareSession()
    {
        $_SESSION[self::$sessionKey] = $_SESSION[self::$sessionKey] ?: [];
    }
    private static function hasSession($key)
    {
        return isset($_SESSION[self::$sessionKey][$key]);
    }
    private static function getSession($key)
    {
        return $_SESSION[self::$sessionKey][$key];
    }
    private static function setSession($key, $value)
    {
        self::prepareSession();
        $_SESSION[self::$sessionKey][$key] = $value;
    }
    private static function unsetSession($key)
    {
        self::prepareSession();
        unset($_SESSION[self::$sessionKey][$key]);
    }

    public static function init()
    {
        $gapperToken = $_GET['gapper-token'];
        if ($gapperToken) {
            \Gini\Gapper\Client::loginByToken($gapperToken);
        } else {
            // 提供第三方登录验证入口
            $third = (array) \Gini\Config::get('gapper.3rd');
            foreach ($third as $key => $value) {
                if (isset($value['condition']) && !$_GET[$value['condition']]) {
                    continue;
                }
                $className = $value['class'];
                if (!class_exists($className)) {
                    continue;
                }
                $handler = \Gini\IoC::construct($className, $value['params']);
                $handler->run();
                $userChanged = true;
            }
        }

        $gapperGroup = $_GET['gapper-group'];
        $currentGapperGroup = self::getGroupID();
        $currentStep = self::getLoginStep();
        if ((!$currentGapperGroup || $gapperGroup && $gapperGroup!=$currentGapperGroup) && in_array($currentStep, [
            self::STEP_GROUP,
            self::STEP_DONE
        ])) {
            $username = self::getUserName();
            self::logout();
            self::loginByUserName($username);
            if (self::getUserName()) self::chooseGroup($gapperGroup);
        }
    }

    public static function getId() {
        return \Gini\Config::get('gapper.rpc')['client_id'] ?: false;
    }

    public static function getInfo($client_id=null, $force=false)
    {
        $client_id = $client_id ?: self::getId();
        if (!$client_id) return [];
        if (self::hasServerAgent()) {
            return self::getAgentAPPInfo($client_id);
        }
        $cacheKey = "app#client#{$client_id}#info";
        if (!$force) {
            $info = self::cache($cacheKey);
            if ($info) return $info;
        }
        $info = self::getRPC()->gapper->app->getInfo($client_id);
        self::cache($cacheKey, $info);
        return $info;
    }

    private static function getAgentAPPInfo($client_id)
    {
        $app = a('gapper/agent/app', ['client_id'=>$client_id]);
        if (!$app->id) return [];
        return [
            'id'=> $app->id,
            'name'=> $app->name,
            'title'=> $app->title,
            'short_title'=> $app->short_title,
            'url'=> $app->url,
            'icon_url'=> $app->icon_url,
            'type'=> $app->type,
            'rate'=> $app->rate,
            'module_name'=> $app->name
        ];
    }

    public static function getLoginStep($force=false)
    {
        $client_id = self::getId();
        if (!$client_id) {
            return self::STEP_LOGIN;
        }

        $app = self::getInfo($client_id, $force);
        if (!isset($app['id'])) {
            return self::STEP_LOGIN;
        }

        $username = self::getUserName();
        if (!$username) {
            return self::STEP_LOGIN;
        }

        if ($app['type'] === 'group' && empty(self::getGroupInfo($force))) {
            $groups = self::getGroups(self::getUserName(), $force);
            if ($groups === false) {
                exit;
            }
            elseif (!empty($groups) && is_array($groups)) {
                return self::STEP_GROUP;
            } else {
                return self::STEP_GROUP_401;
            }
        } elseif ($app['type'] === 'user') {
            $apps = self::getUserApps($username, $force);
            if (!isset($apps[$client_id])) {
                return self::STEP_USER_401;
            }
        }

        return self::STEP_DONE;
    }

    public static function makeUserName($username, $backend=null)
    {
        list($name, $b) = explode('|', $username, 2);
        $backend = $backend ?: ($b ?: 'gapper');
        return "{$name}|{$backend}";
    }

    public static function loginByUserName($username)
    {
        $username = self::makeUserName($username);
        return self::setUserName($username);
    }

    public static function loginByToken($token)
    {
        $user = self::getRPC()->gapper->user->authorizeByToken($token);
        if ($user && $user['username']) {
            $myUsername = self::getUserName();
            if ($myUsername && $user['username']!=$myUsername) {
                \Gini\Gapper\Client::logout();
            }
            return self::loginByUserName($user['username']);
        }

        return false;
    }

    private static $keyUserName = 'username';

    private static function setUserName($username)
    {
        // 错误的client信息，用户无法登陆
        try {
            $app = self::getInfo();
        } catch (\Exception $e) {
        }
        if (!$app['id']) {
            return false;
        }
        self::setSession(self::$keyUserName, $username);

        return true;
    }

    public static function getUserName()
    {
        if (self::hasSession(self::$keyUserName)) {
            $username = self::getSession(self::$keyUserName);
        }

        return $username;
    }

    private static $_userInfo = [];
    public static function getUserInfo($username=null, $force=false)
    {
        $username = $username ?: self::getUserName();
        if (!$username) {
            return;
        }

        $hasServerAgent = self::hasServerAgent();
        // 先检查本地缓存的数据时有没有
        if ($hasServerAgent) {
            $info = self::getAgentUserInfo($username);
            if ($info) return $info;
        }

        $cacheKeyUserName = is_numeric($username) ? $username : self::makeUserName($username);
        $cacheKey = "app#user#{$cacheKeyUserName}#info";
        if (!$force) {
            $info = self::cache($cacheKey);
        }
        if (!$info) {
            $info = self::getRPC()->gapper->user->getInfo($username);
            self::cache($cacheKey, $info);
        }

        // 将数据本地缓存
        if ($hasServerAgent && $info) {
            self::replaceAgentUserInfo($info);
        }

        return $info;
    }

    private static function getAgentUser($username)
    {
        if (is_int($username)) {
            $user = a('gapper/agent/user', ['id'=>$username]);
        } else {
            $username = self::makeUserName($username);
            $user = a('gapper/agent/user', ['username'=>$username]);
        }
        return $user;
    }

    private static function replaceAgentUserInfo($info)
    {
        $user = self::getAgentUser((int)$info['id']);
        if (!$user->id) $user->id = $info['id'];
        $user->name = $info['name'];
        $user->initials = $info['initials'];
        $user->username = $info['username'];
        $user->email = $info['email'];
        $user->phone = $info['phone'];
        $user->icon = $info['icon'];
        $user->stime = date('Y-m-d H:i:s');
        return $user->save();
    }

    private static function getAgentUserInfo($username)
    {
        $user = self::getAgentUser($username);
        if (!$user->id) return;
        return self::makeAgentUserData($user);
    }

    private static function makeAgentUserData($user)
    {
        return [
            'id'=> $user->id,
            'name'=> $user->name,
            'initials'=> $user->initials,
            'username'=> $user->username,
            'email'=> $user->email,
            'phone'=> $user->phone,
            'icon'=> $user->icon,
        ];
    }

    public static function getUserByIdentity($source, $ident, $force=false)
    {
        $hasServerAgent = self::hasServerAgent();
        if ($hasServerAgent) {
            $info = self::getAgentUserByIdentity($source, $ident);
            if ($info) return $info;
        }

        $cacheKey = "app#ident#{$source}#{$ident}#info";
        if (!$force) {
            $info = self::cache($cacheKey);
        }
        if (!$info) {
            $info = self::getRPC()->Gapper->User->getUserByIdentity($source, $ident);
            self::cache($cacheKey, $info);
        }

        if ($hasServerAgent && $info) {
            self::replaceAgentUserIdentity($source, $ident, $info);
        }

        return $info;
    }

    private static function getAgentUserByIdentity($source, $ident)
    {
        $ui = a('gapper/agent/user/identity', ['identity'=> $ident, 'source'=> $source]);
        if (!$ui->id) return;
        $user = a('gapper/agent/user', ['id'=> $ui->user_id]);
        if (!$user->id) return;
        return self::makeAgentUserData($user);
    }

    private static function replaceAgentUserIdentity($source, $ident, $info)
    {
        $user = a('gapper/agent/user', ['id'=> $info['id']]);
        if (!$user->id) {
            if (!self::replaceAgentUserInfo($info)) return;
        }
        $ui = a('gapper/agent/user/identity', ['identity'=> $ident, 'source'=> $source]);
        if ($ui->id) return;
        $ui->source = $source;
        $ui->identity = $ident;
        $ui->user_id = $info['id'];
        return $ui->save();
    }

    public static function linkIdentity($source, $ident, $username=null)
    {
        $username = $username ?: self::getUserName();
        if (!$username) {
            return false;
        }

        return self::getRPC()->Gapper->User->linkIdentity($username, $source, $ident);
    }

    public static function getGroups($username=null, $force=false)
    {
        $client_id = self::getId();
        if (!$client_id) {
            return false;
        }

        $app = self::getInfo($client_id, $force);
        if (!$app['id']) {
            return false;
        }

        $username = $username ?: self::getUserName();
        if (!$username) {
            return false;
        }

        $hasServerAgent = self::hasServerAgent();
        if ($hasServerAgent) {
            $groups = self::getAgentUserGroups($username);
        }

        if (!$groups) {
            $cacheKeyUserName = self::makeUserName($username);
            $cacheKey = "app#user#{$client_id}#{$cacheKeyUserName}#groups";
            $groups = false;
            if (!$force) {
                $groups = self::cache($cacheKey);
            }
            if (false === $groups) {
                $groups = self::getRPC()->gapper->user->getGroups($username) ?: [];
                self::cache($cacheKey, $groups);
            }
            if ($hasServerAgent && $groups) {
                $gids = array_keys($groups);
                $userInfo = self::getUserInfo($username);
                if ($userID = $userInfo['id']) {
                    $db = a('gapper/agent/group/user')->db();
                    foreach ($gids as $gid) {
                        $db->query("insert into gapper_agent_group_user(group_id,user_id) values({$userID}, {$gid})");
                    }
                }
            }
        }

        if (empty($groups)) {
            return [];
        }

        $result = [];
        foreach ($groups as $k => $g) {
            $apps = self::getGroupApps((int)$g['id'], $force);
            if (is_array($apps) && isset($apps[$client_id])) {
                $result[$k] = $g;
            }
        }

        return $result;
    }

    private static function getAgentUserGroups($username)
    {
        $userInfo = self::getUserInfo($username);
        if (!$userInfo) return;
        $userID = (int)$userInfo['id'];
        if (!$userInfo) return;
        $db = a('gapper/agent/group/user')->db();
        $query = $db->query("select group_id from gapper_agent_group_user where user_id={$userID}");
        if (!$query) return;
        $rows = $query->rows();
        if (!count($rows)) return;
        $result = [];
        foreach ($rows as $row) {
            $result[$row->group_id] = self::_getTheGroupInfo((int)$row->group_id);
        }
        return $result;
    }

    private static $keyGroupID = 'groupid';

    public static function resetGroup()
    {
        return self::setSession(self::$keyGroupID, 0);
    }

    public static function chooseGroup($groupID=null, $force=false)
    {
        $client_id = self::getId();
        if (!$client_id) {
            return false;
        }

        $app = self::getInfo($client_id, $force);
        if (!$app['id']) {
            return false;
        }

        $username = self::getUserName();
        if (!$username) {
            return false;
        }

        $cacheKeyUserName = self::makeUserName($username);
        $cacheKey = "app#user#{$client_id}#{$cacheKeyUserName}#groups";
        $groups = false;
        if (!$force) {
            $groups = self::cache($cacheKey);
        }
        if (false === $groups) {
            $groups = self::getRPC()->gapper->user->getGroups($username) ?: [];
            self::cache($cacheKey, $groups);
        }
        if (!is_array($groups)) return false;

        $newGroups = [];
        foreach ($groups as $k => $g) {
            $apps = self::getGroupApps((int)$g['id'], $force);
            if (is_array($apps) && isset($apps[$client_id])) {
                $newGroups[$k] = $g;
            }
        }
        $groups = $newGroups;

        if (!$groupID && count($groups)==1) {
            $groupID = current($groups)['id'];
        }
        if (!in_array($groupID, array_keys($groups))) {
            return false;
        }

        $apps = self::getGroupApps((int)$groupID, $force);
        if (is_array($apps) && in_array($client_id, array_keys($apps))) {
            self::setSession(self::$keyGroupID, $groupID);

            return true;
        }

        return false;
    }

    public static function getUserApps($username=null, $force=false)
    {
        $username = $username ?: self::getUserName();
        $cacheKeyUserName = self::makeUserName($username);
        $cacheKey = "app#user#{$cacheKeyUserName}#apps";
        $apps = false;
        if (!$force) {
            $apps = self::cache($cacheKey);
        }
        if (false===$apps) {
            $apps = (array) self::getRPC()->gapper->user->getApps($username);
            self::cache($cacheKey, $apps);
        }
        return is_array($apps) ? $apps : [];
    }

    public static function getGroupApps($groupID=null, $force=false)
    {
        $groupID = $groupID ?: self::getGroupID();
        if ($groupID) {
            $cacheKey = "app#group#{$groupID}#apps";
            $apps = false;
            if (!$force) {
                $apps = self::cache($cacheKey);
            }
            if (false === $apps) {
                $apps = self::getRPC()->gapper->group->getApps((int)$groupID) ?: [];
                self::cache($cacheKey, $apps);
            }
        }
        return $apps;
    }

    private static function _getCurrentGroupInfo($force=false)
    {
        if (self::hasSession(self::$keyGroupID)) {
            $groupID = self::getSession(self::$keyGroupID);
            if ($groupID) {
                $cacheKey = "app#group#{$groupID}#info";
                return self::_getGroupInfo($cacheKey, (int)$groupID, $force);
            }
        }
    }

    private static function _getTheGroupInfo($criteria, $force=false)
    {
        if (is_numeric($criteria)) {
            $key = $criteria;
        } else {
            $key = md5(J($criteria));
        }
        $cacheKey = "app#group#{$key}#info";
        return self::_getGroupInfo($cacheKey, $criteria, $force);
    }

    private static function _getGroupInfo($cacheKey, $criteria, $force=false)
    {
        $hasServerAgent = self::hasServerAgent();
        if ($hasServerAgent) {
            $info = self::getAgentGroupInfo($criteria);
            if ($info) return $info;
        }

        if (!$force) {
            $info = self::cache($cacheKey);
        }
        if (!$info) {
            $info = self::getRPC()->gapper->group->getInfo($criteria);
            self::cache($cacheKey, $info);
        }

        if ($hasServerAgent && $info) {
            self::replaceAgentGroupInfo($info);
        }

        return $info;
    }

    private static function replaceAgentGroupInfo($info)
    {
        $group = self::getAgentGroup((int)$info['id']);
        if (!$group->id) $group->id = $info['id'];
        $group->name = $info['name'];
        $group->title = $info['title'];
        $group->abbr = $info['abbr'];
        $group->creator = $info['creator'];
        $group->icon = $info['icon'];
        $group->stime = date('Y-m-d H:i:s');
        return $group->save();
    }

    private static function getAgentGroup($criteria)
    {
        if (is_int($criteria)) {
            $group = a('gapper/agent/group', $criteria);
        } else {
            $group = a('gapper/agent/group', ['name'=>$criteria]);
        }
        return $group;
    }

    private static function getAgentGroupInfo($criteria)
    {
        $group = self::getAgentGroup($criteria);
        if (!$group->id) return;
        return self::makeAgentGroupData($group);
    }

    private static function makeAgentGroupData($group)
    {
        return [
            'id'=> $group->id,
            'name'=> $group->name,
            'title'=> $group->title,
            'abbr'=> $group->abbr,
            'creator'=> $group->creator,
            'icon'=> $group->icon,
        ];
    }

    // public static function getGroupInfo($force=false)
    // public static function getGroupInfo(array $criteria=[], $force=false)
    // public static function getGroupInfo($groupID, $force=false)
    public static function getGroupInfo($force=false)
    {
        $args = func_get_args();
        $count = count($args);
        if (!$count || ($count==1 && is_bool($args[0]))) {
            return self::_getCurrentGroupInfo(@$args[0]);
        }

        if ($count==1) {
            return self::_getTheGroupInfo($args[0]);
        }

        if ($count==2) {
            return self::_getTheGroupInfo($args[0], $args[1]);
        }
    }

    public static function getGroupID()
    {
        if (self::hasSession(self::$keyGroupID)) {
            $groupID = self::getSession(self::$keyGroupID);
            $groups = self::getGroups();
            if (is_array($groups) && isset($groups[$groupID])) {
                return $groupID;
            }
        }
    }

    public static function getLoginToken($toClientID, $username=null, $force=false)
    {
        $username = $username ?: self::getUserName();
        $cacheKeyUserName = self::makeUserName($username);
        $cacheKey = "app#user#{$cacheKeyUserName}#{$toClientID}#logintoken";
        if (!$force) {
            $token = self::cache($cacheKey);
        }
        if (!$token) {
            $token = self::getRPC()->gapper->user->getLoginToken($username, $toClientID);
            self::cache($cacheKey, $token);
        }
        return $token;
    }

    public static function logout()
    {
        self::unsetSession(self::$keyGroupID);
        self::unsetSession(self::$keyUserName);

        foreach (array_keys($_SESSION) as $key) {
            if (0!==strpos($key, self::$sessionAuthPrefix)) continue;
            unset($_SESSION[$key]);
        }

        return true;
    }

    public static function goLogin($redirect=null)
    {
        $redirect = $redirect ?: '/';

        if (self::getLoginStep()===self::STEP_GROUP) {
                self::chooseGroup();
        }

        if (self::getLoginStep()===self::STEP_DONE) {
            $url = \Gini\URI::url($redirect, [
                'gapper-token'=> null,
                'gapper-group'=> null
            ]);
        } else {
            $url = \Gini\URI::url('gapper/client/login', ['redirect' => $redirect]);
        }

        \Gini\CGI::redirect($url);
    }

    public static function cache($key, $value=false, $ttl=300) {
        $cacher = \Gini\Cache::of('gapper');
        if (false === $value) {
            return $cacher->get($key);
        }
        $cacher->set($key, $value, $ttl);
    }
}
