<?php

namespace Gini\Controller\CGI\Gapper;

class Client extends \Gini\Controller\CGI\Gapper
{
    private function _checkUrl($base, &$to)
    {
        if (empty($base) || empty($to)) {
            return false;
        }
        $newBase = parse_url($base);
        if (!isset($newBase['scheme']) || !isset($newBase['host'])) {
            return false;
        }

        $newTo = parse_url($to);
        if (!isset($newTo['scheme']) && !isset($newTo['host'])) {
            $newTo = (strpos('/', $to)!==0) ? "/{$to}" : $to;
            $newPort = isset($newBase['port']) ? ":{$newBase['port']}" : '';
            $to = "{$newBase['scheme']}://{$newBase['host']}{$newPort}{$newTo}";
            return true;
        }

        if ($newBase['host'] != $newTo['host']) {
            return false;
        }

        return true;
    }

    public function actionGoHome()
    {
        $paths = func_get_args();
        $homeAPPClientID = \Gini\Config::get('gapper.home_app_client');
        if (!$homeAPPClientID) {
            return $this->_goDefaultHome($paths);
        }

        $username = \Gini\Gapper\Client::getUserName();
        if (!$username) {
            $appInfo = \Gini\Gapper\Client::getInfo($homeAPPClientID);
            $url = $appInfo['url'] ?: '/';
            $this->redirect($url);
            return;
        }
        return $this->actionGo($homeAPPClientID);
    }

    public function actionGoGapperServer()
    {
        $paths = func_get_args();
        return $this->_goDefaultHome($paths);
    }

    private function _goDefaultHome($paths=null)
    {
        $config = (array) \Gini\Config::get('gapper.rpc');
        $client_id = $config['client_id'];
        if (!$client_id) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $url = \Gini\Config::get('gapper.server_home') ?: 'http://gapper.in/';

        if (empty($paths)) {
            $group_id = \Gini\Gapper\Client::getGroupID();
            if ($group_id) {
                $url .= '/dashboard/group/'.$group_id;
            }
        } else {
            $url .= '/'.implode('/', $paths);
        }

        $username = \Gini\Gapper\Client::getUserName();
        if ($username) {
            $token = \Gini\Gapper\Client::getLoginToken($client_id, $username);
        }

        if ($token) {
            $url = \Gini\URI::url($url, 'gapper-token='.$token);
        } else {
            $url = \Gini\URI::url($url);
        }

        return $this->redirect($url);
    }

    public function actionGo($client_id, $group_id = null)
    {
        if (\Gini\Gapper\Client::getLoginStep() !== \Gini\Gapper\Client::STEP_DONE) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $redirect = $_GET['redirect'];

        if (!$client_id) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $app = \Gini\Gapper\Client::getInfo($client_id);
        if (!$app['id'] || !$app['url']) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $username = \Gini\Gapper\Client::getUserName();
        if (!$username) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $token = \Gini\Gapper\Client::getLoginToken($client_id, $username);
        if (!$token) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $url = $app['url'];
        $confs = \Gini\Config::get('gapper.proxy');
        foreach ((array)$confs as $conf) {
            if ($url==$conf['url']) {
                $url = $conf['proxy'] ?: $url;
                break;
            }
        }
        if ($this->_checkUrl($url, $redirect)) {
            $url = $redirect;
        }

        $url = \Gini\URI::url($url, 'gapper-token='.$token);
        if ($group_id) {
            $url = \Gini\URI::url($url, 'gapper-group='.$group_id);
        }

        return $this->redirect($url);
    }

    public function actionLogin()
    {
        $redirect = $_GET['redirect'];
        if (\Gini\Gapper\Client::getLoginStep() === \Gini\Gapper\Client::STEP_DONE) {
            $host = $_SERVER['HTTP_HOST'];
            $redirect = $this->_checkUrl("http://{$host}/", $redirect) ? $redirect : '/';

            return $this->redirect($redirect);
        }

        $view = \Gini\Config::get('gapper.login_view') ?: 'gapper/client/login';
        $this->view->body = VV($view);
    }

    public function actionNoAccount()
    {
        if (!\Gini\Gapper\Client::getId()) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        $app = \Gini\Gapper\Client::getInfo();
        if ($app['type'] == 'group') {
            $view = \Gini\Config::get('gapper.group_account_view') ?: 'gapper/client/group-account';
            parent::setJSVar('ACTION', 'group_account');
            $this->view->body = VV($view);
        } elseif ($app['type'] == 'user') {
            $view = \Gini\Config::get('gapper.user_account_view') ?: 'gapper/client/user-account';
            parent::setJSVar('ACTION', 'user_account');
            $this->view->body = VV($view);
        }
    }

}
