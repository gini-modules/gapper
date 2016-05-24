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
            $rpc->setHeader(["x-gini-session: {$token}"]);
        }
        else {
            $token = $rpc->gapper->app->authorize($client_id, $client_secret);
            if (!$token) {
                \Gini\Logger::of('gapper')->error('Your app was not registered in gapper server!');
            }
            else {
                self::cache($cacheKey, $token);
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

    public static function getInfo($client_id=null)
    {
        $client_id = $client_id ?: self::getId();
        if (!$client_id) return [];
        $cacheKey = "app#client#{$client_id}#info";
        $info = self::cache($cacheKey);
        if ($info) return $info;
        $info = self::getRPC()->gapper->app->getInfo($client_id);
        self::cache($cacheKey, $info);
        return $info;
    }

    public static function getLoginStep()
    {
        $client_id = self::getId();
        if (!$client_id) {
            return self::STEP_LOGIN;
        }

        $app = self::getInfo();
        if (!isset($app['id'])) {
            return self::STEP_LOGIN;
        }

        $username = self::getUserName();
        if (!$username) {
            return self::STEP_LOGIN;
        }

        if ($app['type'] === 'group' && empty(self::getGroupInfo())) {
            $groups = self::getGroups();
            if (!empty($groups) && is_array($groups)) {
                return self::STEP_GROUP;
            } else {
                return self::STEP_GROUP_401;
            }
        } elseif ($app['type'] === 'user') {
            $cacheKey = "app#user#{$username}#apps";
            $apps = self::cache($cacheKey);
            if (empty($apps)) {
                $apps = (array) self::getRPC()->gapper->user->getApps($username);
                self::cache($cacheKey, $apps);
            }
            if (!in_array($client_id, $apps)) {
                return self::STEP_USER_401;
            }
        }

        return self::STEP_DONE;
    }

    public static function loginByUserName($username)
    {
        list($name, $backend) = explode('|', $username, 2);
        $backend = $backend ?: 'gapper';

        return self::setUserName($name.'|'.$backend);
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
    public static function getUserInfo($username=null)
    {
        $username = $username ?: self::getUserName();
        if (!$username) {
            return;
        }

        $cacheKey = "app#user#{$username}#info";
        $info = self::cache($cacheKey);
        if (!$info) {
            $info = self::getRPC()->gapper->user->getInfo([
                'username' => $username,
            ]);
            self::cache($cacheKey, $info);
        }

        return $info;
    }

    public static function getUserByIdentity($source, $ident)
    {
        $cacheKey = "app#ident#{$source}#{$ident}#info";
        $info = self::cache($cacheKey);
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

    public static function getGroups($username=null)
    {
        $client_id = self::getId();
        if (!$client_id) {
            return false;
        }

        $app = self::getInfo();
        if (!$app['id']) {
            return false;
        }

        $username = self::getUserName();
        $username = $username ?: self::getUserName();
        if (!$username) {
            return false;
        }

        $cacheKey = "app#user#{$client_id}#{$username}#groups";
        $groups = self::cache($cacheKey);
        if (empty($groups)) {
            $groups = self::getRPC()->gapper->user->getGroups($username);
            self::cache($cacheKey, $groups);
        }

        if (empty($groups)) {
            return false;
        }

        $result = [];
        foreach ($groups as $k => $g) {
            $apps = self::getGroupApps((int)$g['id']);
            if (is_array($apps) && in_array($client_id, array_keys($apps))) {
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

    public static function chooseGroup($groupID)
    {
        $client_id = self::getId();
        if (!$client_id) {
            return false;
        }

        $app = self::getInfo();
        if (!$app['id']) {
            return false;
        }

        $username = self::getUserName();
        if (!$username) {
            return false;
        }

        $cacheKey = "app#user#{$client_id}#{$username}#groups";
        $groups = self::cache($cacheKey);
        if (empty($groups)) {
            $groups = self::getRPC()->gapper->user->getGroups($username);
            self::cache($cacheKey, $groups);
        }
        if (!is_array($groups) || !in_array($groupID, array_keys($groups))) {
            return false;
        }

        $apps = self::getGroupApps((int)$groupID);
        if (is_array($apps) && in_array($client_id, array_keys($apps))) {
            self::setSession(self::$keyGroupID, $groupID);

            return true;
        }

        return false;
    }

    public static function getGroupApps($groupID=null)
    {
        $groupID = $groupID ?: self::getGroupID();
        $cacheKey = "app#group#{$groupID}#apps";
        $apps = self::cache($cacheKey);
        if (empty($apps)) {
            $apps = self::getRPC()->gapper->group->getApps((int)$groupID);
            self::cache($cacheKey, $apps);
        }
        return $apps;
    }

    public static function getGroupInfo()
    {
        if (self::hasSession(self::$keyGroupID)) {
            $groupID = self::getSession(self::$keyGroupID);
            $cacheKey = "app#group#{$groupID}#info";
            $info = self::cache($cacheKey);
            if (!$info) {
                $info = self::getRPC()->gapper->group->getInfo((int)$groupID);
                self::cache($cacheKey, $info);
            }
        }
        return $info;
    }

    public static function getGroupID()
    {
        if (self::hasSession(self::$keyGroupID)) {
            return self::getSession(self::$keyGroupID);
        }
    }

    public static function getLoginToken($toClientID, $username=null)
    {
        $username = $username ?: self::getUserName();
        $cacheKey = "app#user#{$username}#{$toClientID}#logintoken";
        $token = self::cache($cacheKey);
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

        return true;
    }

    public static function goLogin($redirect=null)
    {
        $url = \Gini\URI::url('gapper/client/login', ['redirect' => $redirect ?: $_SERVER['REQUEST_URI']]);
        \Gini\CGI::redirect($url);
    }

    private static function cache($key, $value=null)
    {
        $cacher = \Gini\Cache::of('gapper');
        if (is_null($value)) {
            return $cacher->get($key);
        }
        $cacher->set($key, $value, 60);
    }
}
