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
    // hasServerAgent:
    //  1: 使用本地的基本数据缓存
    //      app信息缓存: r
    //      token本地化：rw
    //      group 信息缓存: r
    //      user信息缓存: rw
    //      group-app信息缓存: rw
    //      user-app信息缓存: rw
    //      user-identity缓存: rw
    //      group-user关系缓存: r
    //      user-auth用户密码信息: w
    //  30:
    //      user-auth用户密码信息: r
    //

    private static $_has_server_agent = null;
    public static function hasServerAgent()
    {
        if (false===self::$_has_server_agent) return self::$_has_server_agent;

        if (is_null(self::$_has_server_agent)) {
            $dbinfo = \Gini\Config::get('database.gapper-server-agent-db');
            $username = @$dbinfo['username'];
            if (empty($username)) {
                return self::$_has_server_agent = false;
            }
        }

        $config = \Gini\Config::get('gapper.gapper-client-use-agent-data');
        if (!$config) return false;
        return (int)$config;
    }

    private static $_RPC = null;
    public static function getRPC()
    {
        // 如果对接了上海gapper，则不存在失联的情况
        if (\Gini\Config::get('app.gapper_info_from_uniadmin')) {
            $autoUseAgentData = false;
        } else {
            // 需要手动开启gapper.in失联的降级策略
            // 默认不开启
            $autoUseAgentData = !!\Gini\Config::get('gapper.gapper-client-agent-auto-use-agent-data');
        }
        $config = (array) \Gini\Config::get('gapper.rpc');
        $api = $config['url'];
        if (!is_null(self::$_RPC)) {
            $rpc = self::$_RPC;
        } else {
            $rpc = \Gini\IoC::construct('\Gini\RPC', $api);
        }

        if (!$rpc) {
            if ($autoUseAgentData) {
                return self::_get_rpc_failed();
            }
            $rpc = \Gini\IoC::construct('\Gini\RPC', $api);
        }

        $client_id = $config['client_id'];
        $client_secret = $config['client_secret'];

        $sessionID = session_id();
        $networkFailedKey = "app#client#{$client_id}#networkfailed#{$sessionID}";
        if ($autoUseAgentData && self::cache($networkFailedKey)) return self::_get_rpc_failed();

        $cacheKey = "app#client#{$client_id}#session_id";
        $token = self::cache($cacheKey);
        if ($token) {
            $rpc->setHeader(['X-Gini-Session' => $token]);
            self::$_RPC = $rpc;
        } else {
            try {
                $token = $rpc->gapper->app->authorize($client_id, $client_secret);
            } catch (\Exception $e) {
            }
            if (!$token) {
                \Gini\Logger::of('gapper')->error('Your app was not registered in gapper server!');
                self::cache($networkFailedKey, 1, 10);
                if ($autoUseAgentData) self::$_RPC = false;
            } else {
                self::cache($cacheKey, $token, 720);
                $rpc->setHeader(['X-Gini-Session' => $token]);
                self::$_RPC = $rpc;
            }
        }

        if ($autoUseAgentData && !self::$_RPC) return self::_get_rpc_failed();

        return self::$_RPC;
    }

    private static function _get_rpc_failed()
    {
        $config = \Gini\Config::get('gapper.gapper-client-use-agent-data');
        if ($config && (int)$config<30) {
            if (!\Gini\Config::get('app.gapper_info_from_uniadmin')) {
                \Gini\Config::set('gapper.gapper-client-use-agent-data', 30);
                error_log('到gapper.in的链接异常，将使用本地暂存数据暂时保证系统功能正常');
            }
        }
        throw new \Gini\RPC\Exception();
    }

    public static function authorize($clientID, $clientSecret)
    {
        if (self::hasServerAgent()>=1) {
            $db = a('gapper/agent/app')->db();
            $qClientID = $db->quote($clientID);
            $qClientSecret = $db->quote($clientSecret);
            $id = $db->query("select id from gapper_agent_app where client_id={$qClientID} and client_secret={$qClientSecret}")->value();
            if ($id) return true;
            return false;
        }
        try {
            $token = self::getRPC()->gapper->app->authorize($clientID, $clientSecret);
            if ($token) return true;
        } catch (\Exception $e) {
        }
        return false;
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

        try {
            self::getRPC();
        } catch (\Exception $e) {
        }

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

    private static $_agentAPPInfos = [];
    private static function getAgentAPPInfo($client_id)
    {
        if (isset(self::$_agentAPPInfos[$client_id])) return self::$_agentAPPInfos[$client_id];
        $app = a('gapper/agent/app', ['client_id'=>$client_id]);
        if (!$app->id) return [];
        return self::$_agentAPPInfos[$client_id] = self::makeAgentAPPData($app);
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


    private static $keyLoginStatusChanged = 'login-status-changed';
    public static function getLoginStep($force=false)
    {

        if (\Gini\Config::get('gapper.enable-uno-mode') && $_GET['logout'] == true) {
            \Gini\Gapper\Client::logout();
            return self::STEP_LOGIN;
        }

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

        if (self::hasSession(self::$keyLoginStatusChanged)) {
            self::unsetSession(self::$keyLoginStatusChanged);
            \Gini\Event::trigger('gapper.gapper-client-after-user-login');
            \Gini\Event::trigger('gapper.gapper-access-record');
        }

        return self::STEP_DONE;
    }

    public static function makeUserName($username, $backend=null)
    {
        list($name, $b) = explode('|', $username, 2);
        $backend = $backend ?: ($b ?: 'gapper');
        return "{$name}|{$backend}";
    }

    private static function _getUserID()
    {
        $userInfo = self::getUserInfo();
        if (!is_array($userInfo)) return;
        return $userInfo['id'];
    }

    public static function loginByUserID($uid)
    {
        $userInfo = self::getUserInfo($uid);
        if (!is_array($userInfo)) return;
        return self::loginByUserName($userInfo['username']);
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

    private static function _loginByAgentToken($token)
    {
        $config = (array) \Gini\Config::get('gapper.rpc');
        $client_id = $config['client_id'];
        $ut = a('gapper/agent/user/token', ['token'=> $token]);
        if (!$ut->id) return false;
        if ($ut->client_id!=$client_id) return false;
        $tokenLifeTime = \Gini\Config::get('gapper.gapper-client-agent-token-lifetime') ?: 120;
        $etime = date('Y-m-d H:i:s', strtotime("-{$tokenLifeTime} seconds"));
        if ($ut->ctime<$etime) return false;
        $userID = $ut->user_id;
        $currentUserID = self::_getUserID();
        if ($currentUserID && $currentUserID!=$userID) {
            self::logout();
        }
        if ($userID && $currentUserID!=$userID) {
            return self::loginByUserID((int)$userID);
        }
        return true;
    }

    public static function loginByToken($token)
    {
        try {
            self::getRPC();
        } catch (\Exception $e) {
        }

        if (self::hasServerAgent()>=1 && self::_isAgentToken($token)) {
            return self::_loginByAgentToken($token);
        }
        $user = self::getRPC()->gapper->user->authorizeByToken($token);
        if ($user && $user['username']) {
            $myUsername = self::makeUserName(self::getUserName());
            $username = self::makeUserName($user['username']);
            if ($myUsername && $username!=$myUsername) {
                self::logout();
            }
            return self::loginByUserName($username);
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
        self::setSession(self::$keyLoginStatusChanged, true);

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

        try {
            self::getRPC();
        } catch (\Exception $e) {
        }

        // 先检查本地缓存的数据时有没有
        if (self::hasServerAgent()>=1) {
            $info = self::getAgentUserInfo($username);
            if ($info) return $info;
        }

        $cacheKeyUserName = is_numeric($username) ? $username : self::makeUserName($username);
        $cacheKey = "app#user#{$cacheKeyUserName}#info";
        $needAgent = false;
        if (!$force) {
            $info = self::cache($cacheKey);
        }
        if (!$info) {
            try {
                // 判断是否是邮箱 如果是邮箱 ['email'=>$username] 避免混淆 username 和 email
                $pattern = '/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/';
                if (preg_match($pattern, $username)) {
                    $username = ['email'=>$username];
                }
                $info = self::getRPC()->gapper->user->getInfo($username);
                $info = $info ?: [];
                self::cache($cacheKey, $info);
                $needAgent = true;
            } catch (\Exception $e) {
            }
        }

        // 将数据本地缓存
        if (self::hasServerAgent()>=1 && $info && $needAgent) {
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
            'agent_sync_groups'=> $user->agent_sync_groups
        ];
    }

    public static function getUserByIdentity($source, $ident, $force=false)
    {
        try {
            self::getRPC();
        } catch (\Exception $e) {
        }

        if (self::hasServerAgent()>=1) {
            $info = self::getAgentUserByIdentity($source, $ident);
            if ($info) return $info;
        }

        $cacheKey = "app#ident#{$source}#{$ident}#info";
        $needAgent = false;
        if (!$force) {
            $info = self::cache($cacheKey);
        }
        if (!$info) {
            try {
                $info = self::getRPC()->Gapper->User->getUserByIdentity($source, $ident);
                self::cache($cacheKey, $info);
                $needAgent = true;
            } catch (\Exception $e) {
            }

        }

        if (self::hasServerAgent()>=1 && $info && $needAgent) {
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
        if (self::_hasAgent(['user-identity', $source, $ident, $info])) return;
        $user = a('gapper/agent/user', ['id'=> $info['id']]);
        if (!$user->id) {
            if (!self::replaceAgentUserInfo($info)) return;
        }
        $ui = a('gapper/agent/user/identity', ['identity'=> $ident, 'source'=> $source]);
        if ($ui->id) return;
        $ui->source = $source;
        $ui->identity = $ident;
        $ui->user_id = $info['id'];
        $bool = $ui->save();
        if ($bool) {
            self::_recordAgent(['user-identity', $source, $ident, $info]);
        }
        return $bool;
    }

    /**
        * @brief
        *
        * @param $username
        * @param $oldPassword
        * @param $newPassword
        *
        * @return
        *   null: 原始密码错误
        *   false：密码修改失败
        *   true: 密码修改成功
     */
    public static function resetUserPassword($username, $oldPassword, $newPassword)
    {
        if (empty($newPassword)) return false;
        $result = false;
        try {
            if (!self::verfiyUserPassword($username, $oldPassword)) {
                return;
            }
            $info = self::getUserInfo($username);
            if (!isset($info['id'])) return false;
            $token = self::getRPC()->gapper->user->getResetPasswordToken((int)$info['id'], $oldPassword);
            if (!$token) return;
            $result = self::getRPC()->gapper->user->resetPassword($token, $newPassword);
            if ($result) {
                self::_agentUserPassword($username, $newPassword);
            }
        } catch (\Exception $e) {
        }
        return !!$result;
    }

    public static function verfiyUserPassword($username, $password)
    {
        $bool = false;
        $needAgent = false;
        $needCleanAgentPassword = false;
        try {
            $bool = self::getRPC()->gapper->user->verify($username, $password);
            if (!$bool && self::hasServerAgent()>=1) {
                $needCleanAgentPassword = true;
            }
        } catch (\Exception $e) {
            if (self::hasServerAgent()>=30) {
                $needAgent = true;
            }
        }
        if ($bool && self::hasServerAgent()>=1) {
            self::_agentUserPassword($username, $password);
        } else if ($needAgent) {
            $auth = a('gapper/agent/auth', ['username'=>$username]);
            if ($hash=$auth->password) {
                $bool = !!(crypt($password, $hash)==$hash);
            }
        }
        if ($needCleanAgentPassword) {
            $auth = a('gapper/agent/auth', ['username'=>$username]);
            if ($hash=$auth->password) {
                $checked = !!(crypt($password, $hash)==$hash);
                if ($checked) {
                    $auth->delete();
                }
            }
        }
        return !!$bool;
    }

    private static function _agentUserPassword($username, $password)
    {
        if (self::_hasAgent(['user-passowrd', $username, $password])) return;
        $salt = '$6$'.\Gini\Util::randPassword(8, 2).'$';
        $password = crypt($password, $salt);
        $auth = a('gapper/agent/auth', ['username'=>$username]);
        if (!$auth->id) {
            $auth->username = $username;
        }
        $auth->password = $password;
        if ($auth->save()) self::_recordAgent(['user-passowrd', $username, $password]);
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

        try {
            self::getRPC();
        } catch (\Exception $e) {
        }

        if (self::hasServerAgent()>=1) {
            $ui = a('gapper/agent/user/identity', ['user_id'=> $userID, 'source'=> $source]);
            if ($ui->id) return $ui->identity;
        }

        try {
            $identity = self::getRPC()->Gapper->User->getIdentity($username, $source);
        } catch (\Exception $e) {
        }
        if (!$identity) return false;

        if (self::hasServerAgent()>=1) {
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

        try {
            self::getRPC();
        } catch (\Exception $e) {
        }

        if (self::hasServerAgent()>=1) {
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

        if (self::hasServerAgent()>=1) {
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

    public static function updateGroup($id, $data)
    {
        try {
            $groupID = self::getRPC()->gapper->group->update($id, $data);
        } catch (\Exception $e) {
        }
        return $groupID;
    }

    private static function _getHashKey($data)
    {
        return hash('sha1', J($data));
    }

    private static $_agent_keys = [];
    private static function _hasAgent($data)
    {
        $key = self::_getHashKey($data);
        if (isset(self::$_agent_keys[$key])) return true;
        return false;
    }
    private static function _recordAgent($data)
    {
        $key = self::_getHashKey($data);
        self::$_agent_keys[$key] = true;
    }

    private static function _agentUserGroups($username, $groups)
    {
        if (self::_hasAgent(['user-groups', $username, $groups])) return;
        if (empty($groups)) return;
        $user = self::getAgentUser($username);
        if (!$user->id) return;
        $db = $user->db();
        $db->beginTransaction();
        try {
            $user->agent_sync_groups = time();
            if (!$user->save()) throw new \Exception();
            foreach ($groups as $group) {
                if (!$group['id']) continue;
                $bool = $db->query("INSERT IGNORE INTO gapper_agent_group_user(group_id,user_id) VALUES({$group['id']},{$user->id})");
                if (!$bool) throw new \Exception();
            }
            $db->commit();
            self::_recordAgent(['user-groups', $username, $groups]);
        } catch (\Exception $e) {
            $db->rollback();
        }
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

        try {
            self::getRPC();
        } catch (\Exception $e) {
        }

        if (self::hasServerAgent()>=1 && !\Gini\Config::get('app.gapper_info_from_uniadmin')) {
            $userInfo = self::getUserInfo($username);
            if ($userInfo['agent_sync_groups']) {
                $groups = self::getAgentUserGroups($userInfo);
            }
        }

        $needAgent = false;
        if ($force || !$groups) {
            $cacheKeyUserName = self::makeUserName($username);
            $cacheKey = "app#user#{$client_id}#{$cacheKeyUserName}#groups";
            $newgroups = false;
            if (!$force) {
                $newgroups = self::cache($cacheKey);
            }
            if (false === $newgroups) {
                if (!\Gini\Config::get('app.gapper_info_from_uniadmin')) {
                    $needAgent = true;
                }
                try {
                    $filters = [];
                    // if (strpos($app['module_name'], 'admin')===0) {
                    // } else {
                        $filters['type'] = 'lab';
                    //}
                    $newgroups = self::getRPC()->gapper->user->getGroups($username, $filters) ?: [];
                    self::cache($cacheKey, $newgroups);
                } catch (\Exception $e) {
                    $needAgent = false;
                }
            }
            if (!empty($newgroups)) {
                $groups = $newgroups;
            }
        }

        if (empty($groups)) {
            return [];
        }

        $result = [];
        foreach ($groups as $k => $g) {
            if (!$g['id']) continue;
            $apps = self::getGroupApps((int)$g['id'], $force);
            if (!is_array($apps)) {
                $needAgent = false;
                continue;
            }

            $groupHasApp = !!(is_array($apps) && isset($apps[$client_id]));
            $mustInstallApps = (array)\Gini\Config::get('gapper.group_must_install_apps');
            $groupAppsNames = array_keys($apps);
            if (\Gini\Config::get('app.gapper_info_from_uniadmin')) {
                if (in_array($client_id, $mustInstallApps) && !$groupHasApp) continue;
                if (array_intersect($mustInstallApps, $groupAppsNames) && !in_array($client_id, $mustInstallApps)) continue;
                $result[$k] = $g;
            } else if ($groupHasApp) {
                $result[$k] = $g;
            }
        }

        if ($needAgent) {
            self::_agentGroups($groups);
            self::_agentUserGroups($username, $groups);
        }

        return $result;
    }

    private static function _agentGroups($groups)
    {
        if (self::_hasAgent(['groups', $groups])) return;
        $db = a('gapper/agent/group')->db();
        foreach ($groups as $g) {
            if (!$g['id']) continue;
            if ($db->query("select exists(select 1 from gapper_agent_group where id={$g['id']})")->value()) continue;
            self::replaceAgentGroupInfo($g);
        }
        self::_recordAgent(['groups', $groups]);
    }

    private static $_agent_user_groups = [];
    private static function getAgentUserGroups($userInfo)
    {
        if (!$userInfo) return;
        $userID = (int)$userInfo['id'];
        if (!$userID) return;
        if (isset(self::$_agent_user_groups[$userID])) return self::$_agent_user_groups[$userID];
        $db = a('gapper/agent/group/user')->db();
        $query = $db->query("select group_id from gapper_agent_group_user where user_id={$userID}");
        if (!$query) return;
        $rows = $query->rows();
        if (!count($rows)) return;
        $result = [];
        foreach ($rows as $row) {
            $result[$row->group_id] = self::_getTheGroupInfo((int)$row->group_id);
        }
        self::$_agent_user_groups[$userID];
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
            // 选组的时候不需要去拿组成员，这一步，我没想明白当初为什么这么写，但是应该是个废屁的东西
            // // 如果有本地代理数据，需要执行一次getGroupMembers, 因为组在本地缓存的时候，没有缓存组的成员信息
            // if (self::hasServerAgent()>=20) {
            //     self::getGroupMembers((int)$groupID);
            // }
            if ($useUniadminInfo) {
                if (\Gini\Event::get('app.group-auto-install-apps')) {
                    \Gini\Event::trigger('app.group-auto-install-apps', $groupID);
                    self::setSession(self::$keyGroupID, $groupID);
                    self::setSession(self::$keyLoginStatusChanged, true);
                    return true;
                }
                return false;
            }

            self::setSession(self::$keyGroupID, $groupID);
            self::setSession(self::$keyLoginStatusChanged, true);
            return true;
        }

        return false;
    }

    public static function installGroupAPPs(array $appIDs, $groupID)
    {
        if (self::hasServerAgent()>=1) {
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
                    $values[] = $appName;
                }
            }
            self::_agentGroupAPPs($groupID, $values);
        }
        foreach ($appIDs as $appID) {
            if (!self::getRPC()->gapper->app->installTo($appID, 'group', $groupID)) {
                return false;
            }
        }
        return true;
    }

    private static function _agentGroupAPPs($groupID, $appNames)
    {
        if (self::_hasAgent(['group-apps', $groupID, $appNames])) return;
        if (empty($appNames)) return;
        $db = a('gapper/agent/group/app')->db();
        $db->beginTransaction();
        try {
            foreach ($appNames as $appName) {
                $gaString = $db->quote([$groupID, $appName]);
                $bool = $db->query("insert ignore into gapper_agent_group_app(group_id,app_name) values({$gaString})");
                if (!$bool) throw new \Exception();
            }
            $db->commit();
            self::_recordAgent(['group-apps', $groupID, $appNames]);
        } catch (\Exception $e) {
            $db->rollback();
        }
    }

    private static function _agentUserAPPs($userID, $appNames)
    {
        if (self::_hasAgent(['user-apps', $userID, $appNames])) return;
        if (empty($appNames)) return;
        $db = a('gapper/agent/user/app')->db();
        $db->beginTransaction();
        try {
            foreach ($appNames as $appName) {
                $uaString = $db->quote([$userID, $appName]);
                $bool = $db->query("insert ignore into gapper_agent_user_app(user_id,app_name) values({$uaString})");
                if (!$bool) throw new \Exception();
            }
            $db->commit();
            self::_recordAgent(['user-apps', $userID, $appNames]);
        } catch (\Exception $e) {
            $db->rollback();
        }
    }

    public static function installUserAPPs(array $appIDs, $userID)
    {
        if (self::hasServerAgent()>=1) {
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
                    $values[] = $appName;
                }
            }
            self::_agentUserAPPs($userID, $values);
        }
        foreach ($appIDs as $appID) {
            if (!self::getRPC()->gapper->app->installTo($appID, 'user', $userID)) return false;
        }
        return true;
    }

    public static function getUserApps($username=null, $force=false)
    {
        $username = $username ?: self::getUserName();

        try {
            self::getRPC();
        } catch (\Exception $e) {
        }
        if (self::hasServerAgent()>=1) {
            $apps = self::getAgentUserApps($username);
            if ($apps) return $apps;
        }

        $cacheKeyUserName = self::makeUserName($username);
        $cacheKey = "app#user#{$cacheKeyUserName}#apps";
        $apps = false;
        $needAgent = false;
        if (!$force) {
            $apps = self::cache($cacheKey);
        }
        if (false===$apps) {
            try {
                $apps = (array) self::getRPC()->gapper->user->getApps($username);
                self::cache($cacheKey, $apps);
                $needAgent = true;
            } catch (\Exception $e) {
            }
        }

        if (self::hasServerAgent()>=1 && $apps && $needAgent) {
            $db = a('gapper/agent/app')->db();
            $clientIDs = $db->quote(array_keys($apps));
            $query = $db->query("select client_id,name from gapper_agent_app where client_id in ({$clientIDs})");
            if ($query) {
                $rows = $query->rows();
                if (count($rows)) {
                    $user = self::getAgentUser($username);
                    if ($userID=$user->id) {
                        $result = [];
                        $values = [];
                        foreach ($rows as $row) {
                            $clientID = $row->client_id;
                            $values[] = $row->name;
                            $result[$clientID] = $apps[$clientID];
                        }
                        self::_agentUserAPPs($userID, $values);
                        $apps = $result;
                    }
                }
            }
        }
        return is_array($apps) ? $apps : [];
    }

    private static $_agent_user_apps = [];
    private static function getAgentUserApps($username)
    {
        $user = self::getAgentUser($username);
        if (!($userID=$user->id)) return;
        if (isset(self::$_agent_user_apps[$userID])) return self::$_agent_user_apps[$userID];
        $db = a('gapper/agent/user/app')->db();
        $query = $db->query("select gaa.id as id, gaa.client_id as client_id, gaa.name as name, gaa.title as title, gaa.short_title as short_title, gaa.url as url, gaa.icon_url as icon_url, gaa.type as type, gaa.rate as rate, gaa.font_icon as font_icon from gapper_agent_user_app as gua left join gapper_agent_app as gaa on gua.app_name=gaa.name where gua.user_id={$userID}");
        if (!$query) return;
        $rows = $query->rows();
        $apps = [];
        foreach ($rows as $row) {
            $apps[$row->client_id] = self::makeAgentAPPData($row);
        }
        self::$_agent_user_apps[$userID] = $apps;
        return $apps;
    }

    /**
        * @brief
        *
        * @param $groupID
        * @param $criteria
        * @param $start
        * @param $num
        *
        * @return  false | [] | [....]
        * false: 表示本地没有拿到数据
     */
    public static function fetchGroupMembers($groupID, $criteria, $start=0, $num=25)
    {
        $members = self::getGroupMembers($groupID);
        if (empty($members)) return;
        if (!empty($criteria) && is_array($criteria)) {
            $query=trim($criteria['query']);
        }
        $result = [];
        $i = 0;
        $end = $start+$num-1;
        if ($query) {
            foreach ($members as $id=>$member) {
                if ($i<$start || $i>$end) continue;
                $string = implode('|||', [$member['name'],$member['initials'], $member['email']]);
                if (false===mb_strpos($string, $query)) continue;
                $i++;
                $result[$id] = $member;
            }
        } else {
            foreach ($members as $id=>$member) {
                if ($i<$start || $i>$end) continue;
                $i++;
                $result[$id] = $member;
            }
        }
        return $result;
    }

    public static function getGroupMembers($groupID)
    {
        $groupID = (int)$groupID;
        $groupInfo = self::getGroupInfo($groupID, false);
        if (!$groupInfo) return;

        try {
            self::getRPC();
        } catch (\Exception $e) {
        }

        if (self::hasServerAgent()>=1 && !\Gini\Config::get('app.gapper_info_from_uniadmin') && $groupInfo['mstime'] && $groupInfo['agent_sync_members']) {
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

            if (self::hasServerAgent()>=1 && $result) {
                self::_agentGroupMembers($groupID, $result);
            }
        }

        return $result;
    }

    private static function _agentGroupMembers($groupID, $users)
    {
        if (self::_hasAgent(['group-members', $groupID, $users])) return;
        if (empty($users)) return;
        $group = self::getAgentGroup((int)$groupID);
        $db = $group->db();
        $db->beginTransaction();
        try {
            $values = [];
            foreach ($users as $userInfo) {
                $query = $db->query("select exists(select 1 from gapper_agent_group_user where group_id={$groupID} and user_id={$userInfo['id']})");
                if (!$query) continue;
                $values[] = $db->quote([$groupID, $userInfo['id']]);
            }
            if ($values) {
                $valuesStr = implode('),(', $values);
                $bool = $db->query("insert into gapper_agent_group_user (group_id, user_id) values ({$valuesStr})");
                if (!$bool) throw new \Exception();
            }
            if ($group->id) {
                $group->agent_sync_members = time();
                $group->mstime = date('Y-m-d H:i:s', time());
                $bool = $group->save();
                if (!$bool) throw new \Exception();
            }
            $db->commit();
            self::_recordAgent(['group-members', $groupID, $users]);
        } catch (\Exception $e) {
            $db->rollback();
        }
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

        if (self::hasServerAgent()>=1) {
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

        if (self::hasServerAgent()>=1) {
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

        try {
            self::getRPC();
        } catch (\Exception $e) {
        }
        if (self::hasServerAgent()>=1) {
            $db = a('gapper/agent/app')->db();
            // 如果groupID 在黑名单 则不需要查询了
            $query = $db->query("select * from gapper_agent_useless_group where group_id = {$groupID}");
            if ($query) {
                $rows = $query->rows();
                if (count($rows)) {
                    return [];
                }
            }
            $apps = self::getAgentGroupApps((int)$groupID);
            if ($apps) return $apps;
        }

        $cacheKey = "app#group#{$groupID}#apps";
        $apps = false;
        $needAgent = false;
        if (!$force) {
            $apps = self::cache($cacheKey);
        }
        if (false === $apps) {
            try {
                $apps = self::getRPC()->gapper->group->getApps((int)$groupID) ?: [];
                self::cache($cacheKey, $apps);
                $needAgent = true;
            } catch (\Exception $e) {
            }
        }

        if (self::hasServerAgent()>=1 && $needAgent) {
            if (!empty($apps)) {
                $clientIDs = $db->quote(array_keys($apps));
                $query = $db->query("select client_id,name from gapper_agent_app where client_id in ({$clientIDs})");
                if ($query) {
                    $rows = $query->rows();
                    if ($appCount = count($rows)) {
                        $result = [];
                        $values = [];
                        foreach ($rows as $row) {
                            $clientID = $row->client_id;
                            $values[] = $row->name;
                            $result[$clientID] = $apps[$clientID];
                        }
                        self::_agentGroupAPPs($groupID, $values);
                        $apps = $result;
                    }
                }
                if (!$appCount) {
                    $gString = $db->quote([$groupID]);
                    $db->query("insert ignore into gapper_agent_useless_group(group_id) values({$gString})");
                }
            }
        }

        return $apps;
    }

    private static $_agent_group_apps = [];
    private static function getAgentGroupApps($groupID)
    {
        $groupID = (int)$groupID;
        if (isset(self::$_agent_group_apps[$groupID])) return self::$_agent_group_apps[$groupID];
        $db = a('gapper/agent/group/app')->db();
        $query = $db->query("select gaa.id as id, gaa.client_id as client_id, gaa.name as name, gaa.title as title, gaa.short_title as short_title, gaa.url as url, gaa.icon_url as icon_url, gaa.type as type, gaa.rate as rate, gaa.font_icon as font_icon from gapper_agent_group_app as gga left join gapper_agent_app as gaa on gga.app_name=gaa.name where gga.group_id={$groupID}");
        if (!$query) return;
        $rows = $query->rows();
        $apps = [];
        foreach ($rows as $row) {
            $apps[$row->client_id] = self::makeAgentAPPData($row);
        }
        self::$_agent_group_apps[$groupID] = $apps;
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
        try {
            self::getRPC();
        } catch (\Exception $e) {
        }
        if (self::hasServerAgent()>=1 && !\Gini\Config::get('app.gapper_info_from_uniadmin')) {
            $info = self::getAgentGroupInfo($criteria);
            if ($info) return $info;
        }

        $needAgent = false;
        if (!$force) {
            $info = self::cache($cacheKey);
        }
        if (!$info) {
            try {
                $info = self::getRPC()->gapper->group->getInfo($criteria);
                self::cache($cacheKey, $info);
                $needAgent = true;
            } catch (\Exception $e) {
            }
        }

        if (self::hasServerAgent()>=1 && $info && $needAgent) {
            self::replaceAgentGroupInfo($info);
        }

        return $info;
    }

    private static function replaceAgentGroupInfo($info)
    {
        if (self::_hasAgent(['group-info', $info])) return;
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
            self::_recordAgent(['group-info', $info]);
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
            'agent_sync_members'=> $group->agent_sync_members
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

    private static function _isAgentToken($token)
    {
        if (0!==strpos($token, 'code')) return false;
        return true;
    }

    private static function _makeAgentToken()
    {
        return 'code'.sha1(uniqid(microtime(true), true));
    }

    private static function _getAgentUserToken($username, $toClientID, $force)
    {
        $userInfo = self::getUserInfo($username);
        $userID = $userInfo['id'];
        if (!$userID) return;

        $ut = a('gapper/agent/user/token', [
            'user_id'=> $userID,
            'client_id'=> $toClientID
        ]);
        $tokenLifeTime = \Gini\Config::get('gapper.gapper-client-agent-token-lifetime') ?: 120;
        $tokenLifeTime -= 40;
        $etime = date('Y-m-d H:i:s', strtotime("-{$tokenLifeTime} seconds"));
        if (!$ut->id || $force || $ut->ctime<$etime) {
            $ut->user_id = $userID;
            $ut->client_id = $toClientID;
            $token = self::_makeAgentToken();
            $ut->token = $token;
            $ut->ctime = date('Y-m-d H:i:s');
            $ut->save();
        }
        return $ut->token;
    }

    public static function getLoginToken($toClientID, $username=null, $force=false)
    {
        $username = $username ?: self::getUserName();
        try {
            self::getRPC();
        } catch (\Exception $e) {
        }
        if (self::hasServerAgent()>=1) {
            $appInfo = self::getInfo($toClientID);
            // TODO 目前labmai这边mall-old的功能很快会被替代掉，再去改mall-old的代码风险比较高
            // 所以，如果是跳去mall-old，就不要用本地token了
            if ($appInfo['module_name']!='mall-old') {
                return self::_getAgentUserToken($username, $toClientID, $force);
            }
        }
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
