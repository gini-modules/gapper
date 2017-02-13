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
                self::cache($cacheKey, $token, 700);
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
            \Gini\Gapper\Client::logout();
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
            }
        }

        $gapperGroup = $_GET['gapper-group'];
        if ($gapperGroup && \Gini\Gapper\Client::getLoginStep() === \Gini\Gapper\Client::STEP_GROUP) {
            \Gini\Gapper\Client::chooseGroup($gapperGroup);
        }
    }

    public static function getId() {
        return \Gini\Config::get('gapper.rpc')['client_id'] ?: false;
    }

    public static function getInfo($client_id=null, $force=false)
    {
        $client_id = $client_id ?: self::getId();
        if (!$client_id) return [];
        $cacheKey = "app#client#{$client_id}#info";
        if (!$force) {
            $info = self::cache($cacheKey);
            if ($info) return $info;
        }
        $info = self::getRPC()->gapper->app->getInfo($client_id);
        self::cache($cacheKey, $info);
        return $info;
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
            $cacheKeyUserName = self::makeUserName($username);
            $cacheKey = "app#user#{$cacheKeyUserName}#apps";
            if (!$force) {
                $apps = self::cache($cacheKey);
            }
            if (empty($apps)) {
                $apps = (array) self::getRPC()->gapper->user->getApps($username);
                self::cache($cacheKey, $apps);
            }
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

        $cacheKeyUserName = is_numeric($username) ? $username : self::makeUserName($username);
        $cacheKey = "app#user#{$cacheKeyUserName}#info";
        if (!$force) {
            $info = self::cache($cacheKey);
        }
        if (!$info) {
            $info = self::getRPC()->gapper->user->getInfo($username);
            self::cache($cacheKey, $info);
        }

        return $info;
    }

    public static function getUserByIdentity($source, $ident, $force=false)
    {
        $cacheKey = "app#ident#{$source}#{$ident}#info";
        if (!$force) {
            $info = self::cache($cacheKey);
        }
        if (!$info) {
            $info = self::getRPC()->Gapper->User->getUserByIdentity($source, $ident);
            self::cache($cacheKey, $info);
        }
        return $info;
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

    private static $keyGroupID = 'groupid';

    public static function resetGroup()
    {
        return self::setSession(self::$keyGroupID, 0);
    }

    public static function chooseGroup($groupID, $force=false)
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
        if (!is_array($groups) || !in_array($groupID, array_keys($groups))) {
            return false;
        }

        $apps = self::getGroupApps((int)$groupID, $force);
        if (is_array($apps) && in_array($client_id, array_keys($apps))) {
            self::setSession(self::$keyGroupID, $groupID);

            return true;
        }

        return false;
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
        if (!$force) {
            $info = self::cache($cacheKey);
        }
        if (!$info) {
            $info = self::getRPC()->gapper->group->getInfo($criteria);
            self::cache($cacheKey, $info);
        }
        return $info;
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
        $redirect = $redirect ?: $_SERVER['REQUEST_URI'];

        if (self::getLoginStep()===self::STEP_GROUP) {
            $groups = self::getGroups();
            if ($groups && count($groups)==1) {
                self::chooseGroup(current($groups)['id']);
            }
        }

        if (self::getLoginStep()===self::STEP_DONE) {
            $url = \Gini\URI::url($redirect, [
                'gapper-token'=> '',
                'gapper-group'=> ''
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
