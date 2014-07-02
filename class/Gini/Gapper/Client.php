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
    private static $_RPC = [];
    private function getRPC($type='gapper')
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
        $_SESSION[self::$sessionKey][$key] = $value;
    }
    private static function unsetSession($key)
    {
        unset($_SESSION[self::$sessionKey][$key]);
    }

    public function __construct($mustLogin=false)
    {
        if ($mustLogin) {
            self::login();
        }
    }

    public static function login()
    {
        $isLoggedIn = \Gini\Auth::isLoggedIn();
        if (!$isLoggedIn) {
            self::prepareSession();
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
        \Gini\Auth::logout();
        $url = \Gini\Config::get('gapper.logout_url');
        \Gini\CGI::redirect($url);
    }

    public function getCurrentUserName()
    {
        return \Gini\Auth::userName();
    }

    public function getUserInfo()
    {
        if (!$this->getCurrentUserName()) return;
        try {
            $data = $this->getRPC()->user->getInfo([
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
        try {
            $groupID = $_GET['gapper-group'];
            if ($groupID || !self::hasSession($key)) {
                // groups: [group->id,...]
                $groups = $this->getRPC()->user->getGroupIDs($this->getCurrentUserName());
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
            $data = $this->getRPC()->group->getInfo($id);
            return $data;
        }
        catch (\Gini\RPC\Exception $e) {
        }
    }
}
