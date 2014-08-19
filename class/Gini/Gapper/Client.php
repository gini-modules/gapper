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
    const STEP_LOGIN = 0;
    const STEP_GROUP = 1;

    private static $_RPC = [];
    private static function getRPC($type='gapper')
    {
        if (!self::$_RPC[$type]) {
            try {
                $api = \Gini\Config::get($type . '.url');
                $client_id = \Gini\Config::get($type . '.client_id');
                $client_secret = \Gini\Config::get($type . '.client_secret');
                $rpc = \Gini\IoC::construct('\Gini\RPC', $api, $type);
                $bool = $rpc->authorize($client_id, $client_secret);
                if (!$bool) {
                    throw new \Exception('Your APP was not registered in gapper server!');
                }
            } catch (\Gini\RPC\Exception $e) {
            }

            self::$_RPC[$type] = $rpc;
        }

        return self::$_RPC[$type];
    }

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

    public function __construct()
    {
    }

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
        /*
        try {
            $groupID = $_GET['gapper-group'];
            if ($groupID || !self::hasSession($key)) {
                // groups: [group->id,...]
                $groups = self::getRPC()->user->getGroupIDs($this->getCurrentUserName());
                if (is_array($groups)) switch (count($groups)) {
                    case 0:
                        self::setSession($key, '');
                        break;
                    case 1:
                        self::setSession($key, array_pop($groups));
                        break;
                    default:
                        if ($groupID && in_array($groupID, $groups)) {
                            self::setSession($key, $groupID);
                        }
                        else {
                            // redirect to choose group
                            $url = \Gini\Config::get('gapper.choose_group_url');
                            \Gini\CGI::redirect($url, [
                                'redirect_url'=> URL('', $_GET)
                                ,'client_id'=> \Gini\Config::get('gapper.client_id')
                            ]);
                        }
                        break;
                }
            }

            $id = self::getSession($key);
            $data = self::getRPC()->group->getInfo($id);
            return $data;
        }
        catch (\Gini\RPC\Exception $e) {
        }
        */
    }
}
