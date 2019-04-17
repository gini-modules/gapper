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

        $config = \Gini\Config::get('gapper.gapper-client-use-agent-data');
        if (!$config) {
            self::$_has_server_agent = false;
        } else {
            self::$_has_server_agent = (int)$config;
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
        if (self::hasServerAgent()>=1) {
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
        return self::makeAgentAPPData($app);
    }

    private static function makeAgentAPPData($app)
    {
        return [
            'id'=> $app->id,
            'name'=> $app->name,
            'title'=> $app->title,
            'short_title'=> $app->short_title,
            'url'=> $app->url,
            'icon_url'=> $app->icon_url,
            'type'=> $app->type,
            'rate'=> $app->rate,
            'font_icon'=> $app->font_icon,
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

        $loginByOauth = \Gini\Config::get('app.node_is_login_by_oauth');
        if ($loginByOauth) {
            if (self::needLogout()) {
                self::logout();
                return self::STEP_LOGIN;
            }
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
        // sso-logout, 用户登录时清理sso登出信息
        if (\Gini\Config::get('app.node_is_login_by_oauth')) {
            self::unsetBECache($username);
        }
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
        if ($hasServerAgent>=1) {
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
            $info = $info ?: [];
            self::cache($cacheKey, $info);
        }

        // 将数据本地缓存
        if ($hasServerAgent>=1 && $info) {
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
        if ($hasServerAgent>=1) {
            $info = self::getAgentUserByIdentity($source, $ident);
            if ($info) return $info;
        }

        $cacheKey = "app#ident#{$source}#{$ident}#info";
        if (!$force) {
            $info = self::cache($cacheKey);
        }
        if (!$info) {
            try {
                $info = self::getRPC()->Gapper->User->getUserByIdentity($source, $ident);
                self::cache($cacheKey, $info);
            } catch (\Exception $e) {
            }

        }

        if ($hasServerAgent>=1 && $info) {
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

    public static function verfiyUserPassword($username, $password)
    {
        try {
            return self::getRPC()->gapper->user->verify($username, $password);
        } catch (\Exception $e) {
        }
        return false;
    }

    public static function registerUserWithIdentity($data, $source, $identity)
    {
        return self::registerUser($data, $source, $identity);
    }

    public static function registerUser($data, $source=null, $identity=null)
    {
        try {
            if ($source && $identity) {
                $uid = self::getRPC()->gapper->user->registerUserWithIdentity($data, $source, $identity);
            } else {
                $uid = self::getRPC()->gapper->user->registerUser($data);
            }
        } catch (\Exception $e) {
        }
        return $uid;
    }

    public static function getIdentity($username, $source)
    {
        if (!$username) return;
        $userInfo = self::getUserInfo($username);
        if (!($userID=$userInfo['id'])) return;

        $hasServerAgent = self::hasServerAgent();
        if ($hasServerAgent>=1) {
            $ui = a('gapper/agent/user/identity', ['user_id'=> $userID, 'source'=> $source]);
            if ($ui->id) return $ui->identity;
        }

        try {
            $identity = self::getRPC()->Gapper->User->getIdentity($username, $source);
        } catch (\Exception $e) {
        }
        if (!$identity) return false;

        if ($hasServerAgent>=1) {
            $ui = a('gapper/agent/user/identity');
            $ui->source = $source;
            $ui->identity = $identity;
            $ui->user_id = $userID;
            $ui->save();
        }
        return $identity;
    }

    public static function linkIdentity($source, $ident, $username=null)
    {
        $username = $username ?: self::getUserName();
        if (!$username) {
            return false;
        }

        $userInfo = self::getUserInfo($username);
        if (!($userID=$userInfo['id'])) return false;

        $hasServerAgent = self::hasServerAgent();
        if ($hasServerAgent>=1) {
            $ui = a('gapper/agent/user/identity', ['identity'=>$ident, 'source'=> $source]);
            if ($ui->id) {
                if ($ui->user_id == $userID) return true;
                return false;
            }
        }

        try {
            $result = self::getRPC()->Gapper->User->linkIdentity($username, $source, $ident);
        } catch (\Exception $e) {
        }
        if (!$result) return false;

        if ($hasServerAgent>=1) {
            $ui = a('gapper/agent/user/identity');
            $ui->identity = $ident;
            $ui->source = $source;
            $ui->user_id = $userID;
            $ui->save();
        }

        return true;
    }

    public static function createGroup($data)
    {
        try {
            $groupID = self::getRPC()->gapper->group->create($data);
        } catch (\Exception $e) {
        }
        return $groupID;
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
        if ($hasServerAgent>=20) {
            $groups = self::getAgentUserGroups($username);
        }

        if ($force || !$groups) {
            $cacheKeyUserName = self::makeUserName($username);
            $cacheKey = "app#user#{$client_id}#{$cacheKeyUserName}#groups";
            $newgroups = false;
            if (!$force) {
                $newgroups = self::cache($cacheKey);
            }
            if (false === $newgroups) {
                try {
                    $newgroups = self::getRPC()->gapper->user->getGroups($username) ?: [];
                    self::cache($cacheKey, $newgroups);
                } catch (\Exception $e) {
                }
            }
            if (!empty($newgroups)) $groups = $newgroups;
        }

        if (empty($groups)) {
            return [];
        }

        $result = [];
        $userGroupIDs = [];
        foreach ($groups as $k => $g) {
            $apps = self::getGroupApps((int)$g['id'], $force);
            if (\Gini\Config::get('app.gapper_info_from_uniadmin') || (is_array($apps) && isset($apps[$client_id]))) {
                $result[$k] = $g;
            }
            if ($hasServerAgent>=20) {
                foreach ($apps as $clientID=>$app) {
                    if (self::getAgentAPPInfo($clientID)) {
                        $userGroupIDs[] = $g;
                        break;
                    }
                }
            }
        }

        if ($hasServerAgent>=20 && $userGroupIDs) {
            $db = a('gapper/agent/group/user')->db();
            $db->beginTransaction();
            foreach ($userGroupIDs as $g) {
                if ($db->query("select exists(select 1 from gapper_agent_group where id={$g['id']})")->value()) continue;
                self::replaceAgentGroupInfo($g);
            }
            $db->commit();
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

        $groups = self::getGroups($username, true);
        if (!$groups) return false;

        if (!$groupID && count($groups)==1) {
            $groupID = current($groups)['id'];
        }
        if (!in_array($groupID, array_keys($groups))) {
            return false;
        }

        $apps = self::getGroupApps((int)$groupID, $force);
        $useUniadminInfo = \Gini\Config::get('app.gapper_info_from_uniadmin');
        if (($groupID && $useUniadminInfo) || (is_array($apps) && in_array($client_id, array_keys($apps)))) {
            // 如果有本地代理数据，需要执行一次getGroupMembers, 因为组在本地缓存的时候，没有缓存组的成员信息
            if (self::hasServerAgent()>=20) {
                self::getGroupMembers((int)$groupID);
            }
            if ($useUniadminInfo) {
                if (\Gini\Event::get('app.group-auto-install-apps')) {
                    \Gini\Event::trigger('app.group-auto-install-apps', $groupID);
                    self::setSession(self::$keyGroupID, $groupID);
                    return true;
                }
                return false;
            }

            self::setSession(self::$keyGroupID, $groupID);
            return true;
        }

        return false;
    }

    public static function installGroupAPPs(array $appIDs, $groupID)
    {
        $hasServerAgent = self::hasServerAgent();
        if ($hasServerAgent>=1) {
            $db = a('gapper/agent/app')->db();
            $appString = $db->quote($appIDs);
            $query = $db->query("select client_id,name,type from gapper_agent_app where client_id in ({$appString})");
            if (!$query) return false;
            $rows = $query->rows();
            if (!count($rows)) return false;
            $appIDs = [];
            $values = [];
            foreach ($rows as $row) {
                if ($row->type!='group') continue;
                $appIDs[] = $row->client_id;
                $appName = $row->name;
                $qAN = $db->quote($appName);
                $bool = $db->query("select id from gapper_agent_group_app where group_id={$groupID} and app_name={$qAN}")->value();
                if (!$bool) {
                    $values[] = '(' . $db->quote([$groupID, $appName]) . ')';
                }
            }
            if ($values) {
                $valueString = implode(',', $values);
                $bool = $db->query("insert into gapper_agent_group_app(group_id,app_name) values {$valueString}");
                if (!$bool) return false;
            }
        }
        foreach ($appIDs as $appID) {
            if (!self::getRPC()->gapper->app->installTo($appID, 'group', $groupID)) {
                return false;
            }
        }
        return true;
    }

    public static function installUserAPPs(array $appIDs, $userID)
    {
        $hasServerAgent = self::hasServerAgent();
        if ($hasServerAgent>=1) {
            $db = a('gapper/agent/app')->db();
            $appString = $db->quote($appIDs);
            $query = $db->query("select client_id,name,type from gapper_agent_app where client_id in ({$appString})");
            if (!$query) return false;
            $rows = $query->rows();
            if (!count($rows)) return false;
            $appIDs = [];
            $values = [];
            foreach ($rows as $row) {
                if ($row->type!='user') continue;
                $appIDs[] = $row->client_id;
                $appName = $row->name;
                $qAN = $db->quote($appName);
                $bool = $db->query("select id from gapper_agent_user_app where group_id={$userID} and app_name={$qAN}")->value();
                if (!$bool) {
                    $values[] = '(' . $db->quote([$userID, $appName]) . ')';
                }
            }
            if ($values) {
                $valueString = implode(',', $values);
                $bool = $db->query("insert into gapper_agent_user_app(user_id,app_name) values {$valueString}");
                if (!$bool) return false;
            }
        }
        foreach ($appIDs as $appID) {
            if (!self::getRPC()->gapper->app->installTo($appID, 'user', $userID)) return false;
        }
        return true;
    }

    public static function getUserApps($username=null, $force=false)
    {
        $username = $username ?: self::getUserName();

        $hasServerAgent = self::hasServerAgent();
        if ($hasServerAgent>=1) {
            $apps = self::getAgentUserApps($username);
            if ($apps) return $apps;
        }

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

        if ($hasServerAgent>=1 && $apps) {
            $db = a('gapper/agent/app')->db();
            $clientIDs = $db->quote(array_keys($apps));
            $query = $db->query("select client_id,name from gapper_agent_app where client_id in ({$clientIDs})");
            if ($query) {
                $rows = $query->rows();
                if (count($rows)) {
                    $user = self::getAgentUser($username);
                    if ($userID=$user->id) {
                        $result = [];
                        $db->beginTransaction();
                        foreach ($rows as $row) {
                            $clientID = $row->client_id;
                            $app_name = $row->name;
                            $result[$clientID] = $apps[$clientID];
                            $uaString = $db->quote([$userID, $app_name]);
                            $db->query("insert ignore into gapper_agent_user_app(user_id,app_name) values({$uaString})");
                        }
                        $db->commit();
                        $apps = $result;
                    }
                }
            }
        }
        return is_array($apps) ? $apps : [];
    }

    private static function getAgentUserApps($username)
    {
        $user = self::getAgentUser($username);
        if (!($userID=$user->id)) return;
        $db = a('gapper/agent/user/app')->db();
        $query = $db->query("select gaa.id as id, gaa.client_id as client_id, gaa.name as name, gaa.title as title, gaa.short_title as short_title, gaa.url as url, gaa.icon_url as icon_url, gaa.type as type, gaa.rate as rate, gaa.font_icon as font_icon from gapper_agent_user_app as gua left join gapper_agent_app as gaa on gua.app_name=gaa.name where gua.user_id={$userID}");
        if (!$query) return;
        $rows = $query->rows();
        $apps = [];
        foreach ($rows as $row) {
            $apps[$row->client_id] = self::makeAgentAPPData($row);
        }
        return $apps;
    }

    public static function getGroupMembers($groupID)
    {
        $groupID = (int)$groupID;
        $groupInfo = self::getGroupInfo($groupID, false);
        if (!$groupInfo) return;

        $hasServerAgent = self::hasServerAgent();
        if ($hasServerAgent>=20) {
            if ($groupInfo && $groupInfo['mstime']) {
                $db = a('gapper/agent/group/user')->db();
                $query = $db->query("select user_id from gapper_agent_group_user where group_id={$groupID}");
                if ($query) {
                    $rows = $query->rows();
                    if (count($rows)) {
                        foreach ($rows as $row) {
                            $result[$row->user_id] = self::getUserInfo((int)$row->user_id);
                        }
                    }
                }
            }
        }

        if (!$result) {
            $start = 0;
            $per_page = 50;
            $result = [];
            while (true) {
                $members = (array) self::getRPC()->gapper->group->getMembers((int)$groupID, null ,$start, $per_page);
                $start += $per_page;
                if (!count($members)) break;
                $result = $result + $members;
            }

            if ($hasServerAgent>=20 && $result) {
                $db = a('gapper/agent/group/user')->db();
                $db->beginTransaction();
                $values = [];
                foreach ($result as $userInfo) {
                    if ($db->query("select exists(select 1 from gapper_agent_group_user where group_id={$groupID} and user_id={$userInfo['id']})")->value()) continue;
                    $values[] = $db->quote([$groupID, $userInfo['id']]);
                }
                if ($values) {
                    $valuesStr = implode('),(', $values);
                    $db->query("insert into gapper_agent_group_user (group_id, user_id) values ({$valuesStr})");
                }
                $db->query("update gapper_agent_group set mstime=CURRENT_TIMESTAMP where id={$groupID}");
                $db->commit();
            }
        }

        return $result;
    }

    public static function addGroupMember($groupID, $userID)
    {
        $groupID = (int)$groupID;
        $userID = (int)$userID;
        $userInfo = self::getUserInfo($userID);
        if (!$userInfo) return false;

        try {
            $bool = self::getRPC()->gapper->group->addMember($groupID, $userID);
        } catch (\Exception $e) {
        }

        if (!$bool) return false;

        if (self::hasServerAgent()>=20) {
            $db = a('gapper/agent/group/user')->db();
            if ($db->query("select exists(select 1 from gapper_agent_group_user where group_id={$groupID} and user_id={$userID})")->value()) return true;
            return !!($db->query("insert into gapper_agent_group_user (group_id, user_id) values({$groupID}, {$userID})"));
        } 
        self::getGroups($userInfo['username'], true);
        return true;
    }

    public static function removeGroupMember($groupID, $userID)
    {
        $groupID = (int)$groupID;
        $userID = (int)$userID;
        $userInfo = self::getUserInfo($userID);
        if (!$userInfo) return false;
        
        try {
            $bool = self::getRPC()->gapper->group->removeMember($groupID, $userID);
        } catch (\Exception $e) {
        }

        if (!$bool) return false;

        if (self::hasServerAgent()>=20) {
            $db = a('gapper/agent/group/user')->db();
            return !!($db->query("delete from gapper_agent_group_user where group_id={$groupID} and user_id={$userID}"));
        } 
        self::getGroups($userInfo['username'], true);
        return true;
    }

    public static function getGroupApps($groupID=null, $force=false)
    {
        $groupID = $groupID ?: self::getGroupID();
        if (!$groupID) return;

        $hasServerAgent = self::hasServerAgent();
        if ($hasServerAgent>=1) {
            $apps = self::getAgentGroupApps((int)$groupID);
            if ($apps) return $apps;
        }

        $cacheKey = "app#group#{$groupID}#apps";
        $apps = false;
        if (!$force) {
            $apps = self::cache($cacheKey);
        }
        if (false === $apps) {
            $apps = self::getRPC()->gapper->group->getApps((int)$groupID) ?: [];
            self::cache($cacheKey, $apps);
        }

        if ($hasServerAgent>=1 && $apps) {
            $db = a('gapper/agent/app')->db();
            $clientIDs = $db->quote(array_keys($apps));
            $query = $db->query("select client_id,name from gapper_agent_app where client_id in ({$clientIDs})");
            if ($query) {
                $rows = $query->rows();
                if (count($rows)) {
                    $result = [];
                    $db->beginTransaction();
                    foreach ($rows as $row) {
                        $clientID = $row->client_id;
                        $app_name = $row->name;
                        $result[$clientID] = $apps[$clientID];
                        $gaString = $db->quote([$groupID, $app_name]);
                        $db->query("insert ignore into gapper_agent_group_app(group_id,app_name) values({$gaString})");
                    }
                    $db->commit();
                    $apps = $result;
                }
            }
        }

        return $apps;
    }

    private static function getAgentGroupApps($groupID)
    {
        $groupID = (int)$groupID;
        $db = a('gapper/agent/group/app')->db();
        $query = $db->query("select gaa.id as id, gaa.client_id as client_id, gaa.name as name, gaa.title as title, gaa.short_title as short_title, gaa.url as url, gaa.icon_url as icon_url, gaa.type as type, gaa.rate as rate, gaa.font_icon as font_icon from gapper_agent_group_app as gga left join gapper_agent_app as gaa on gga.app_name=gaa.name where gga.group_id={$groupID}");
        if (!$query) return;
        $rows = $query->rows();
        $apps = [];
        foreach ($rows as $row) {
            $apps[$row->client_id] = self::makeAgentAPPData($row);
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
        if ($hasServerAgent>=1) {
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

        if ($hasServerAgent>=1 && $info) {
            self::replaceAgentGroupInfo($info);
        }

        return $info;
    }

    private static function replaceAgentGroupInfo($info)
    {
        $group = self::getAgentGroup((int)$info['id']);
        if (!$group->id) {
            $group->id = $info['id'];
        }
        $group->name = $info['name'];
        $group->title = $info['title'];
        $group->abbr = $info['abbr'];
        $group->creator = $info['creator'];
        $group->icon = $info['icon'];
        $group->stime = date('Y-m-d H:i:s');
        if ($group->save()) {
            return true;
        }
        return false;
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
            'mstime'=> $group->mstime,
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

    public static function needLogout()
    {
        if (!\Gini\Config::get('app.node_is_login_by_oauth')) return false;
        $username = self::getUserName();
        if (!$username) return false;
        $time = \Gini\Cache::of('sso-msg')->get('msg-sso-logout-'.md5($username));
        if (!$time) return false;
        return true;
    }

    public static function setBELogout($username)
    {
        if (!\Gini\Config::get('app.node_is_login_by_oauth')) return;
        $username = self::makeUserName($username);
        $defaultTimeout = ini_get('session.gc_maxlifetime') ?: 1440;
        $bool = \Gini\Cache::of('sso-msg')->set('msg-sso-logout-'.md5($username), time(), (int)($defaultTimeout * 1.5));
    }

    public static function unsetBECache($username)
    {
        $username = self::makeUserName($username);
        \Gini\Cache::of('sso-msg')->remove('msg-sso-logout-'.md5($username));
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
        $redirectURL = '/';
        if ($redirect) {
            $redirectURL = $redirect; 
        } else if (\Gini\Config::get('app.node_is_login_by_oauth')) {
            $uri = parse_url($_SERVER['HTTP_REFERER']);
            parse_str($uri['query'], $uriData);
            if ($uriData['redirect']) {
                $redirectURL = \Gini\URI::url($uriData['redirect']);
            } else {
                $pathInfo = $_SERVER['PATH_INFO'];
                $redirectURL = $pathInfo ? \Gini\URI::url($pathInfo) : '/';
            }
        }

        if (self::getLoginStep()===self::STEP_GROUP) {
                self::chooseGroup();
        }

        if (self::getLoginStep()===self::STEP_DONE) {
            $url = \Gini\URI::url($redirectURL, [
                'gapper-token'=> null,
                'gapper-group'=> null
            ]);
        } else {
            $url = \Gini\URI::url('gapper/client/login', ['redirect' => $redirectURL]);
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
