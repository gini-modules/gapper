<?php
/**
* @file Client.php
* @brief APP <--> Gapper Client <--> Gapper Server
* @author Hongjie Zhu
* @version 0.1.0
* @date 2014-06-18
 */

/**
 *
 * $client = \Gini\IoC::construct('\Gini\Gapper\Client', true); (default)
 * $client = \Gini\IoC::construct('\Gini\Gapper\Client', false);
 * $username = $client->getCurrentUserName();
 * $userdata = $client->getUserInfo();
 * $groupdata = $client->getGroupInfo();
 *
 */
namespace Gini\Gapper;

class Client
{

    use \Gini\Module\Gapper\Client\RPCTrait;

    const STEP_LOGIN = 0;
    const STEP_GROUP = 1;
    const STEP_DONE = 2;

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

    public static function getLoginStep()
    {
        // 错误的client信息，用户无法登陆
        $client_id = \Gini\Config::get('gapper.client_id');
        if (!$client_id) return self::STEP_LOGIN;
        $app = self::getRPC()->app->getInfo($client_id);
        if (!$app['id']) return self::STEP_LOGIN;

        $username = self::getUserName();
        if (!$username) return self::STEP_LOGIN;

        if ($app['type']==='group' && empty(self::getGroupInfo())) {
            return self::STEP_GROUP;
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
        $client_id = \Gini\Config::get('gapper.client_id');
        $user = self::getRPC()->user->authorizeByToken($token, $client_id);
        if ($user && $user['username']) {
            return self::loginByUserName($user['username']);
        }
        return false;
    }

    private static $keyUserName = 'username';

    private static function setUserName($username)
    {
        // 错误的client信息，用户无法登陆
        $client_id = \Gini\Config::get('gapper.client_id');
        if (!$client_id) return false;
        $app = self::getRPC()->app->getInfo($client_id);
        if (!$app['id']) return false;
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
        if (!self::getUserName()) return;
        try {
            $data = self::getRPC()->user->getInfo([
                'username'=> self::getUserName()
            ]);
        }
        catch (\Gini\RPC\Exception $e) {
        }
        return $data;
    }

    public static function getGroups()
    {
        $client_id = \Gini\Config::get('gapper.client_id');
        if (!$client_id) return false;

        $app = self::getRPC()->app->getInfo($client_id);
        if (!$app['id']) return false;

        $username = self::getUserName();
        if (!$username) return false;

        $groups = self::getRPC()->user->getGroups($username);
        if (empty($groups)) return false;

        $result = [];
        foreach ($groups as $k=>$g) {
            $apps = self::getRPC()->group->getApps((int)$g['id']);
            if (is_array($apps) && in_array($client_id, array_keys($apps))) {
                $result[$k] = $g;
            }
        }

        return $result;
    }

    private static $keyGroupID = 'groupid';

    public static function chooseGroup($groupID)
    {
        $client_id = \Gini\Config::get('gapper.client_id');
        if (!$client_id) return false;
        $app = self::getRPC()->app->getInfo($client_id);
        if (!$app['id']) return false;

        $username = self::getUserName();
        if (!$username) return false;

        $groups = self::getRPC()->user->getGroups($username);
        if (!is_array($groups) || !in_array($groupID, array_keys($groups))) {
            return false;
        }

        $apps = self::getRPC()->group->getApps((int)$groupID);
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
            $data = self::getRPC()->group->getInfo((int)$groupID);
            return $data;
        }
    }

    public static function getGroupID()
    {
        if (self::hasSession(self::$keyGroupID)) {
            $groupID = self::getSession(self::$keyGroupID);
            return $groupID;
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
        $url = \Gini\URI::url('gapper/client/login', ['redirect'=> $_SERVER['REQUEST_URI']]);
        \Gini\CGI::redirect($url);
    }

    /*
    // 暂时保留一下API，待APP升级结束，再进行删除
    public static function isLoggedIn()
    {
        return self::getLoginStep()===self::STEP_DONE;
    }

    // 暂时保留一下API，待APP升级结束，再进行删除
    public static function login()
    {
        return self::goLogin();
    }
     */

    /*
    public static function getLoginStep()
    {
        if (!\Gini\Auth::isLoggedIn()) return self::STEP_LOGIN;

        $key = 'isLoggedIn';
        $isLoggedIn = self::getSession($key);
        if (!$isLoggedIn) return self::STEP_GROUP;
    }

    public static function chooseGroup($gid)
    {
        $_GET['gapper-group'] = $gid;
        return self::isLoggedIn();
    }

    public static function isLoggedIn()
    {
        if (!\Gini\Auth::isLoggedIn()) return false;

        $key = 'isLoggedIn';
        $isLoggedIn = self::getSession($key);
        if ($isLoggedIn) return true;

        $client_id = \Gini\Config::get('gapper.client_id');
        if (!$client_id) return false;

        $app = self::getRPC()->app->getInfo($client_id);
        if (!$app['id']) return false;

        $username = \Gini\Auth::userName();
        if ($app['type'] == 'user') {
            $apps = self::getRPC()->user->getApps($username);
            if (is_array($apps) && in_array($client_id, array_keys($apps))) {
                // 当前用户已经添加了该app
                self::setSession($key, 1);
                return true;
            }
        }
        elseif ($app['type'] == 'group') {
            $groups = self::getRPC()->user->getGroups($username);
            if (!is_array($groups)) return false;
            $group = (int)$_GET['gapper-group'];

            if (in_array($group, array_keys($groups))) {
                $group = $groups[$group];
            }
            elseif (count($groups)==1) {
                $group = array_pop($groups);
            }
            else {
                $group = null;
            }

            if ($group) {
                $apps = self::getRPC()->group->getApps((int)$group['id']);
                if (is_array($apps) && in_array($client_id, array_keys($apps))) {
                    // 当前组已经添加了该app
                    self::setSession($key, 1);
                    self::setSession('groupid', (int)$group['id']);
                    return true;
                }
            }
        }

        return false;
    }

    public static function login()
    {
        return self::loginByToken();
    }

    public static function loginByToken()
    {
        // 已经登录成功，直接返回
        if (self::isLoggedIn()) return true;

        // 通过token登录成功
        $token = $_GET['gapper-token'];
        if ($token) {
            $client_id = \Gini\Config::get('gapper.client_id');
            $user = self::getRPC()->user->authorizeByToken($token, $client_id);
            if ($user && $user['username']) {
                \Gini\Auth::login(\Gini\Auth::makeUserName($user['username']));
                if (self::isLoggedIn()) return true;
            }
        }

        // 展示登录界面，让用户提交信息登录
        $url = 'gapper/client/login?redirect='.$_SERVER['REQUEST_URI'];
        \Gini\CGI::redirect($url);
    }

    public static function loginByOAuth()
    {
        $isLoggedIn = \Gini\Auth::isLoggedIn();
        if (!$isLoggedIn) {
            $key = 'isLogging';
            if (!self::hasSession($key)) {
                $oauthSSO = 'gapper/'.uniqid();
                self::setSession($key, $oauthSSO);
            }
            else {
                $oauthSSO = self::getSession($key);
            }
            //var_dump($oauthSSO);die;
            $oauth = \Gini\IoC::construct('\Gini\OAuth\Client', $oauthSSO);
            $username = $oauth->getUserName();
            //var_dump($username);die;
            \Gini\Auth::login($username);
            self::unsetSession($key);
        }
        return !!\Gini\Auth::userName();
    }

    public static function logout()
    {
        self::unsetSession('isLoggedIn');
        self::unsetSession('groupid');
        \Gini\Auth::logout();
        $url = 'gapper/client/login';
        \Gini\CGI::redirect($url);
    }

    public function getCurrentUserName()
    {
        if (self::isLoggedIn()) {
            return \Gini\Auth::userName();
        }
    }

    public function getUserInfo()
    {
        if (!$this->getCurrentUserName()) return;
        try {
            $data = self::getRPC()->user->getInfo([
                'username'=> $this->getCurrentUserName()
            ]);
        }
        catch (\Gini\RPC\Exception $e) {
        }
        return $data;
    }

    public function getGroupInfo()
    {
        if (!$this->getCurrentUserName()) return;
        $key = 'groupid';
        if (self::hasSession($key)) {
            $id = self::getSession($key);
            $data = self::getRPC()->group->getInfo($id);
            return $data;
        }
    }
     */
}
