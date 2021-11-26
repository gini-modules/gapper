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
namespace Gini\Controller\CGI\AJAX\Gapper;

class Client extends \Gini\Controller\CGI
{
    /**
     * @brief 获取等咯过程中各个阶段的数据
     *
     * @return
     */
    public function actionSources()
    {
        $current = \Gini\Gapper\Client::getLoginStep();

        $data = [];
        switch ($current) {
        case \Gini\Gapper\Client::STEP_LOGIN:
            return \Gini\CGI::request('ajax/gapper/step/login', $this->env)->execute();
        case \Gini\Gapper\Client::STEP_GROUP:
            return \Gini\CGI::request('ajax/gapper/step/group', $this->env)->execute();
        case \Gini\Gapper\Client::STEP_USER_401:
            return \Gini\CGI::request('ajax/gapper/step/user401', $this->env)->execute();
        case \Gini\Gapper\Client::STEP_GROUP_401:
            return \Gini\CGI::request('ajax/gapper/step/group401', $this->env)->execute();
        case \Gini\Gapper\Client::STEP_DONE:
            return \Gini\CGI::request('ajax/gapper/step/done', $this->env)->execute();
        }
    }

    public function actionLogout()
    {
        \Gini\Gapper\Client::logout();
        if (\Gini\Config::get('app.node_is_login_by_oauth')) {
            // 去gateway中删除登陆
            $clientID = \Gini\Config::get('gapper.rpc')['client_id'];
            $gatewayLogoutURL = \Gini\Config::get('oauth.client')['servers']['gateway']['logout'];
            $redirectURL  = \Gini\URI::url($gatewayLogoutURL, [
                'redirect' => \Gini\URI::url('/'),
                'client' => $clientID
            ]);
            $data = [
                'redirect' => $redirectURL
            ];
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
        }
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', true);
    }

    public function actionGetAddMemberTypes()
    {
        $current = \Gini\Gapper\Client::getLoginStep();
        if ($current!==\Gini\Gapper\Client::STEP_DONE) return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');

        $app = \Gini\Gapper\Client::getInfo();
        if (strtolower($app['type'])!='group') return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');

        $conf = (array) \Gini\Config::get('gapper.auth');
        $data = [];
        foreach ($conf as $type=>$info) {
            $handler = $info['add_member_handler'] ?: "\Gini\Controller\CGI\AJAX\Gapper\Auth\\{$type}::addmember";
            if (is_callable($handler)) {
                if (($info['show'] !== false) || ($info['adduser'] !== false)) {
                    $data[$type] = $info;
                }
            }
        }
        if (!empty($data)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('gapper/client/add-member-types', [
                'data'=> $data,
                'group'=> \Gini\Gapper\Client::getGroupID()
            ]));
        }
    }

    public function actionGetAddModal()
    {
        $form = $this->form('post');
        $conf = (array) \Gini\Config::get('gapper.auth');
        $type = $form['type'];
        $gid = $form['gid'];
        if ($gid!=\Gini\Gapper\Client::getGroupID()) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $info = $conf[$type];
        if (empty($info)) return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');

        $handler = $info['add_member_handler'] ?: "\Gini\Controller\CGI\AJAX\Gapper\Auth\\{$type}::addmember";
        if (!is_callable($handler)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        return call_user_func_array($handler, ['get-add-modal', $type, $gid]);
    }

    public function actionSearch()
    {
        $data = $this->form('get');
        $value = $data['value'];
        $type = $data['type'];
        if (!$value) return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        $conf = (array) \Gini\Config::get('gapper.auth');
        $info = $conf[$type];
        if (empty($info)) return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        $handler = $info['add_member_handler'] ?: "\Gini\Controller\CGI\AJAX\Gapper\Auth\\{$type}::addmember";
        if (!is_callable($handler)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        return call_user_func_array($handler, ['search', $type, $value]);
    }

    public function actionPostAdd()
    {
        $form = $this->form('post');
        $username = $form['username'];
        if (empty($username)) return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        $get = $this->form('get');
        $type = $get['type'];
        $conf = (array) \Gini\Config::get('gapper.auth');
        $info = $conf[$type];
        if (empty($info)) return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        $handler = $info['add_member_handler'] ?: "\Gini\Controller\CGI\AJAX\Gapper\Auth\\{$type}::addmember";
        if (!is_callable($handler)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        return call_user_func_array($handler, ['post-add', $type, $form]);
    }
}
