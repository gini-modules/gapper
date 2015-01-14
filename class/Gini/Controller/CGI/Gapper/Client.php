<?php

namespace Gini\Controller\CGI\Gapper;

class Client extends \Gini\Controller\CGI\Gapper
{

    use \Gini\Module\Gapper\Client\RPCTrait;

    private function _showNothing()
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
    }

    private function _checkUrl($base, $to)
    {
        if (empty($base) || empty($to)) return false;
        $base = parse_url($base);
        $to = parse_url($to);
        if ($base['host']!=$to['host']) return false;
        return true;
    }

    public function actionGoHome()
    {
        $paths = func_get_args();

        $config = (array) \Gini\Config::get('gapper.rpc');
        $client_id = $config['client_id'];
        if (!$client_id) return $this->_showNothing();

        $url = \Gini\Config::get('gapper.server_home') ?: 'http://gapper.in/';

        if (empty($paths)) {
            $group_id = \Gini\Gapper\Client::getGroupID();
            if ($group_id) {
                $url .= '/dashboard/group/' . $group_id;
            }
        }
        else {
            $url .= '/' . join('/', $paths);
        }

        $user = \Gini\Gapper\Client::getUserInfo();
        if ($user['id']) {
            $token = self::getRPC()->user->getLoginToken((int) $user['id'], $client_id);
        }

        if ($token) {
            $url = \Gini\URI::url($url, 'gapper-token='.$token);
        }
        else {
            $url = \Gini\URI::url($url);
        }

        return $this->redirect($url);
    }

    public function actionGo($client_id)
    {
        if (\Gini\Gapper\Client::getLoginStep()!==\Gini\Gapper\Client::STEP_DONE) return $this->_showNothing();

        $redirect = $_GET['redirect'];

        if (!$client_id) {
            return $this->_showNothing();
        }
        $app = self::getRPC()->app->getInfo($client_id);
        if (!$app['id']) {
            return $this->_showNothing();
        }
        $user = \Gini\Gapper\Client::getUserInfo();
        if (!$user['id']) {
            return $this->_showNothing();
        }
        $token = self::getRPC()->user->getLoginToken((int) $user['id'], $client_id);
        if (!$token) {
            return $this->_showNothing();
        }
        $url = $app['url'];
        if ($this->_checkUrl($url, $redirect)) {
            $url = $redirect;
        }

        $url = \Gini\URI::url($url, 'gapper-token='.$token);

        return $this->redirect($url);
    }

    public function actionLogin()
    {
        $redirect = $_GET['redirect'];
        if (\Gini\Gapper\Client::getLoginStep()===\Gini\Gapper\Client::STEP_DONE) {
            $redirect = $this->_checkUrl('/', $redirect) ? $redirect : '/';

            return $this->redirect($redirect);
        }

        $view = \Gini\Config::get('gapper.login_view') ?: 'gapper/client/login' ;
        $this->view->body = VV($view);
    }
}
