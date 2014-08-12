<?php

namespace Gini\Controller\CGI\Gapper;

class Client extends \Gini\Controller\CGI\Gapper
{
    private function _getRPC()
    {
        return parent::getRPC();
    }
    
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

    public function actionGo($client_id)
    {
        $redirect = $_GET['redirect'];

        if (!$client_id || !\Gini\Auth::isLoggedIn()) {
            return $this->_showNothing();
        }
        $app = $this->_getRPC()->app->getInfo($client_id);
        if (!$app['id']) {
            return $this->_showNothing();
        }
        $token = $this->_getRPC()->user->getLoginToken($client_id);
        $username = \Gini\Auth::userName();
        $url = $app['url'];
        if (!$username || !$token) {
            return $this->_showNothing();
        }
        if ($this->_checkUrl($url, $redirect)) {
            $url = $redirect;
        }

        $url = \Gini\URI::url($url, 'login-token='.$token);

        return $this->redirect($url);
    }

    public function actionLogin()
    {
        $redirect = $_GET['redirect'];
        if (\Gini\Auth::isLoggedIn()) {
            $redirect = $this->_checkUrl('/', $redirect) ? $redirect : '/';
            return $this->redirect($redirect);
        }

        $sources = (array)\Gini\Config::get('gapperauth');

        $data = [];
        foreach ($sources as $source=>$info) {
            $key = strtolower("GapperAuth" . str_replace('-', '', $source));
            $data[$key] = $info;
        }

        $this->view->body = VV('gapper/client/login', ['sources'=>$data]);
    }
}

