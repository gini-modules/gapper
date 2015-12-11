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
    use \Gini\Module\Gapper\Client\RPCTrait;

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

    public static function getLoginStep()
    {
        // 错误的client信息，用户无法登陆
        $config = \Gini\Config::get('gapper.rpc');
        $client_id = $config['client_id'];
        if (!$client_id) {
            return self::STEP_LOGIN;
        }

        $app = self::getRPC()->gapper->app->getInfo($client_id);
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
            $apps = (array) self::getRPC()->gapper->user->getApps(self::getUserName());
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
        $config = \Gini\Config::get('gapper.rpc');
        $client_id = $config['client_id'];
        if (!$client_id) {
            return false;
        }
        try {
            $app = self::getRPC()->gapper->app->getInfo($client_id);
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

    public static function getUserInfo()
    {
        if (!self::getUserName()) {
            return;
        }

        $data = self::getRPC()->gapper->user->getInfo([
            'username' => self::getUserName(),
        ]);

        return $data;
    }

    public static function getGroups()
    {
        $config = \Gini\Config::get('gapper.rpc');
        $client_id = $config['client_id'];
        if (!$client_id) {
            return false;
        }

        $app = self::getRPC()->gapper->app->getInfo($client_id);
        if (!$app['id']) {
            return false;
        }

        $username = self::getUserName();
        if (!$username) {
            return false;
        }

        $groups = self::getRPC()->gapper->user->getGroups($username);
        if (empty($groups)) {
            return false;
        }

        $result = [];
        foreach ($groups as $k => $g) {
            $apps = self::getRPC()->gapper->group->getApps((int) $g['id']);
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
        $config = (array) \Gini\Config::get('gapper.rpc');
        $client_id = $config['client_id'];
        if (!$client_id) {
            return false;
        }

        $app = self::getRPC()->gapper->app->getInfo($client_id);
        if (!$app['id']) {
            return false;
        }

        $username = self::getUserName();
        if (!$username) {
            return false;
        }

        $groups = self::getRPC()->gapper->user->getGroups($username);
        if (!is_array($groups) || !in_array($groupID, array_keys($groups))) {
            return false;
        }

        try {
            $apps = self::getRPC()->gapper->group->getApps((int) $groupID);
        } catch (\Exception $e) {
        }
        if (is_array($apps) && in_array($client_id, array_keys($apps))) {
            self::setSession(self::$keyGroupID, $groupID);

            return true;
        }

        return false;
    }

    public static function getGroupInfo()
    {
        if (self::hasSession(self::$keyGroupID)) {
            $groupID = self::getSession(self::$keyGroupID);
            return self::getRPC()->gapper->group->getInfo((int) $groupID);
        }
    }

    public static function getGroupID()
    {
        if (self::hasSession(self::$keyGroupID)) {
            return self::getSession(self::$keyGroupID);
        }
    }

    public static function logout()
    {
        self::unsetSession(self::$keyGroupID);
        self::unsetSession(self::$keyUserName);

        return true;
    }

    public static function goLogin()
    {
        $redirect = $_SERVER['REQUEST_URI'];
        $redirect = \Gini\RUI::url($redirect, [
            'gapper_token'=> '',
            'gapper_group'=> ''
        ]);
        $url = \Gini\URI::url('gapper/client/login', ['redirect' => $redirect]);
        \Gini\CGI::redirect($url);
    }
}
