<?php

namespace Gini\Controller\CGI\Gapper;

class Client extends \Gini\Controller\CGI\Gapper
{
    use \Gini\Module\Gapper\Client\RPCTrait;

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

        $user = \Gini\Gapper\Client::getUserInfo();
        if ($user['id']) {
            $token = self::getRPC()->gapper->user->getLoginToken((int) $user['id'], $client_id);
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
        $app = self::getRPC()->gapper->app->getInfo($client_id);
        if (!$app['id']) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        $user = \Gini\Gapper\Client::getUserInfo();
        if (!$user['id']) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        $token = self::getRPC()->gapper->user->getLoginToken((int) $user['id'], $client_id);
        if (!$token) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $url = $app['url'];
        $confs = \Gini\Config::get('gapper.proxy');
        foreach ($confs as $conf) {
            if ($url==$conf['url']) {
                $url = $conf['proxy'] ?: $url;
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
            $port = $_SERVER['SERVER_PORT'];
            $port = $port=='80' ? '' : ":{$port}";
            $redirect = $this->_checkUrl("http://{$host}{$port}/", $redirect) ? $redirect : '/';

            return $this->redirect($redirect);
        }

        $view = \Gini\Config::get('gapper.login_view') ?: 'gapper/client/login';
        $this->view->body = VV($view);
    }
    public function actionNoAccount()
    {
        $config = (array) \Gini\Config::get('gapper.rpc');
        $client_id = $config['client_id'];
        if (!$client_id) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }
        $app = self::getRPC()->gapper->app->getInfo($client_id);
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
