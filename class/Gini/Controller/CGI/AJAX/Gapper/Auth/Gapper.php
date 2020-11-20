<?php

/**
 * @file Client.php
 * @brief 用户登录
 *
 * @author Hongjie Zhu
 *
 * @version 0.1.0
 * @date 2015-01-08
 */
namespace Gini\Controller\CGI\AJAX\Gapper\Auth;

class Gapper extends \Gini\Controller\CGI
{
    private function _showJSON($data)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
    }

    private function _showHTML($view, array $data = [])
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V($view, $data));
    }
    private function loginByAccessToken($accessToken)
    {
        $rest = new \Gini\HTTP();
        $rest->header('X-Gapper-OAuth-Token', $accessToken);
        $config = \Gini\Config::get('api.uniadmin-access-agent-config');
        $responseData = $rest->get($config['url']. '/v1/current-user', []);
        $userInfo = @json_decode($responseData, true);
        $userID = $userInfo['global_id'] ?: $userInfo['id'];
        if ($userID) {
            \Gini\Gapper\Client::loginByUserID($userID);
        }
        return $userID?true:false;
    }

    public function actionLoginByAccessToken()
    {
        $form = $this->form();
        $accessToken = $form['accessToken'];
        $ret = $this->loginByAccessToken($accessToken);
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $ret);
    }

    /**
     * @brief 展示登录表单
     *
     * @return
     */
    public function actionGetForm()
    {
        $conf = (array) \Gini\Config::get('gapper.auth');

        return $this->_showHTML('gapper/auth/gapper/login', [
            'info' => (object) \Gini\Config::get('gapper.auth')['gapper'],
            'hasMultiLogType' => !!(count(array_keys($conf)) > 1)
        ]);
    }
    public function actionGetGroupAccount()
    {
        return $this->_showHTML('gapper/auth/gapper/group-account');
    }
    public function actionGetUserAccount()
    {
        return $this->_showHTML('gapper/auth/gapper/user-account');
    }

    /**
     * @brief 登录
     *
     * @return
     */
    public function actionLogin()
    {
        if (\Gini\Gapper\Client::getLoginStep() === \Gini\Gapper\Client::STEP_DONE) {
            return $this->_showJSON(true);
        }

        $form = $this->form('post');
        $username = $form['username'];
        $password = $form['password'];
        $captcha = $form['captcha'];

        if (!$captcha) {
            return $this->_showJSON('captcha required! ');
        }

        if ($captcha !== $_SESSION['gapper_authcode']) {
            return $this->_showJSON('captcha wrong! Please try again');
        }

        if (\Gini\Gapper\Client::verfiyUserPassword($username, $password)) {
            $result = \Gini\Gapper\Client::loginByUserName($username);
            if ($result) {
                // by pihizi
                // 登录成功直接返回TRUE, 又有有前端页面控制如何进行接下来的行为
                // return $this->_showJSON(true);
                // 我现在对他做如下修改:
                //   登录成功，直接当前页面选组
                $bool = \Gini\CGI::request('ajax/gapper/client/sources', $this->env)->execute()->content();
                if ($bool!==true && $bool) {
                    if (is_array($bool)) {
                        return $this->_showJSON($bool);
                    }
                    return $this->_showJSON([
                        'type'=> 'modal',
                        'message'=> (string)$bool
                    ]);
                }
                return \Gini\CGI::request('ajax/gapper/step/done', $this->env)->execute();
            }
        }

        return $this->_showJSON(T('Login failed! Please try again.'));
    }

    /**
     * @brief 选择组
     *
     * @return
     */
    public function actionChoose()
    {
        $current = \Gini\Gapper\Client::getLoginStep();
        if ($current !== \Gini\Gapper\Client::STEP_GROUP) {
            return $this->_showJSON(T('Access Denied!'));
        }

        $form = $this->form('post');
        if (!isset($form['id'])) {
            return $this->_showJSON(T('Access Denied!'));
        }

        $bool = \Gini\Gapper\Client::chooseGroup($form['id']);
        if ($bool) {
            return \Gini\CGI::request('ajax/gapper/step/done', $this->env)->execute();
        }

        return $this->_showJSON(T('Access Denied!'));
    }

    public static function addMember()
    {
        $args = func_get_args();
        $action = array_shift($args);
        switch ($action) {
        case 'get-add-modal':
            return call_user_func_array([self, '_getAddModal'], $args);
            break;
        case 'search':
            return call_user_func_array([self, '_getSearchResults'], $args);
            break;
        case 'post-add':
            return call_user_func_array([self, '_postAdd'], $args);
            break;
        }
    }

    private static function _getAddModal($type, $groupID)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('gapper/client/add-member/modal'));
    }

    private static function _getSearchResults($type, $email)
    {
        $pattern = '/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/';
        if (!preg_match($pattern, $email)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        try {
            $info = \Gini\Gapper\Client::getUserInfo($email);
        } catch (\Exception $e) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        if ($info && $info['id']) {
            try {
                $groups = \Gini\Gapper\Client::getGroups($email, true);
            } catch (\Exception $e) {
                return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
            }
            $current = \Gini\Gapper\Client::getGroupID();
            // 一卡通用户已经在当前组
            if (isset($groups[$current])) {
                return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
            }
            $data = [
                'username'=> $email,
                'name'=> $info['name'],
                'initials'=> $info['initials'],
                'icon'=> $info['icon']
            ];
        }
        else {
            $data = [
                'username'=> $email,
                'name'=> null,
                'email'=> $email
            ];
        }

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', (string)V('gapper/client/add-member/match', $data));
    }

    private static function _postAdd($type, $form)
    {
        $username = $form['username'];
        try {
            $info = \Gini\Gapper\Client::getUserInfo($username);
        } catch (\Exception $e) {
            return self::_alert(T('操作失败，请您重试'));
        }

        $current = \Gini\Gapper\Client::getGroupID();

        if ($info['id']) {
            try {
                $groups = \Gini\Gapper\Client::getGroups($username, true);
            } catch (\Exception $e) {
                return self::_alert(T('操作失败，请您重试'));
            }
            if (isset($groups[$current])) {
                return self::_success($info);
            }

            if (\Gini\Gapper\Client::addGroupMember((int)$current, (int)$info['id'])) return self::_success($info);

            return self::_alert(T('操作失败，请您重试'));
        }

        // 如果没有提交email和name, 展示确认name和email的表单
        if (empty($form['name']) || empty($form['email'])) {
            $error = [];
            if (empty($form['name'])) {
                $error['name'] = T('请补充用户姓名');
            }
            if (empty($form['email'])) {
                $error['email'] = T('请填写Email');
            }
        }

        $pattern = '/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/';
        if ($form['email'] && !preg_match($pattern, $form['email'])) {
            $error['email'] = T('请填写真实的Email');
        }

        if (!preg_match($pattern, $username)) {
            return self::_alert(T('操作失败，请您重试'));
        }

        if (!empty($error)) {
            return self::_showFillInfo([
                'username'=> $username,
                'name'=> $form['name'],
                'email'=> $form['email'],
                'error'=> $error
            ]);
        }

        $email = $form['email'];
        $name = $form['name'];
        if ($username!=$email) {
            try {
                $info = \Gini\Gapper\Client::getUserInfo($email);
            } catch (\Exception $e) {
                return self::_alert(T('操作失败，请您重试'));
            }
            if ($info['id']) {
                return self::_showFillInfo([
                    'username'=> $username,
                    'name'=> $name,
                    'email'=> $email,
                    'error'=> [
                        'email'=> 'Email已经被占用, 请换一个试试!'
                    ]
                ]);
            }
        }

        $uid = \Gini\Gapper\Client::registerUser([
            'username'=> $username,
            'password'=> \Gini\Util::randPassword(),
            'name'=> $name,
            'email'=> $email
        ]);

        if (!$uid) return self::_alert(T('添加用户失败, 请重试!'));

        if (\Gini\Gapper\Client::addGroupMember((int)$current, (int)$uid)) {
            $info = \Gini\Gapper\Client::getUserInfo($email);
            return self::_success($info);
        }

        return self::_alert(T('一卡通用户已经激活, 但是暂时无法将该用户加入当前组, 请联系网站管理员处理!'));
    }

    private static function _success(array $user=[])
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'replace',
            'replace'=> $user,
            'message'=> (string)V('gapper/client/add-member/success')
        ]);
    }

    private static function _alert($message)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'alert',
            'message'=> $message
        ]);
    }

    private static function _showFillInfo($vars)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'replace',
            'message'=> (string)V('gapper/client/add-member/fill-info', $vars)
        ]);
    }
}
