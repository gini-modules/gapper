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

class Step extends \Gini\Controller\CGI
{
    private function _showJSON($data)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
    }

    private function _showHTML($view, array $data = [])
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V($view, $data));
    }

    public function actionLogin()
    {
        $conf = (array) \Gini\Config::get('gapper.auth');
        $sources = [];
        foreach ($conf as $key => $info) {
            $key = strtolower($key);
            $info['name'] = T($info['name']);
            $sources[$key] = $info;
        }

        if (count($sources) == 1) {
            return $this->_showHTML('gapper/auth/gapper/login', [
                'info' => (object) \Gini\Config::get('gapper.auth')['gapper'],
                'hasMultiLogType' => !!(count(array_keys($conf)) > 1)
            ]);
        }

        return $this->_showHTML('gapper/client/checkauth', ['sources' => $sources]);
    }

    public function actionGroup()
    {
        $result = $this->_trySourceMethod('group');
        if ($result) return $result;

        $groups = \Gini\Gapper\Client::getGroups();
        if ($groups && count($groups) == 1) {
            $bool = \Gini\Gapper\Client::chooseGroup(current($groups)['id']);
            if ($bool) {
                return \Gini\CGI::request('ajax/gapper/step/done', $this->env)->execute();
            }
        }

        $data['groups'] = $groups;

        return $this->_showHTML('gapper/client/checkgroup', $data);
    }

    public function actionUser401() 
    {
        $result = $this->_trySourceMethod('user401');
        if ($result) return $result;

        \Gini\Gapper\Client::logout();
        $view = \Gini\Config::get('gapper.views')['client/error/401-user'] ?: 'gapper/client/error/401-user';

        return $this->_showHTML($view);
    }

    public function actionGroup401()
    {
        $result = $this->_trySourceMethod('group401');
        if ($result) return $result;

        \Gini\Gapper\Client::logout();
        $view = \Gini\Config::get('gapper.views')['client/error/401-group'] ?: 'gapper/client/error/401-group';

        return $this->_showHTML($view);
    }

    public function actionDone()
    {
        $result = $this->_trySourceMethod('groupDone');
        if ($result) return $result;

        $referer = parse_url(\Gini\URI::url($_SERVER['HTTP_REFERER']));
        $query = $referer['query'];
        parse_str($query, $params);
        $redirectURL = \Gini\URI::url($params['redirect']?:'/');
        return $this->_showJSON([
            'redirect'=> $redirectURL,
            'message'=> (string)V('gapper/client/redirect')
        ]);
    }

    private function _trySourceMethod($method)
    {
        $source = Auth::getSource();
        if ($source) {
            $className = "\\Gini\\Controller\\CGI\\AJAX\\Gapper\\Auth\\{$source}\\Step\\{$method}";
            if (class_exists($className)) {
                return \Gini\CGI::request("ajax/gapper/auth/{$source}/step/{$method}", $this->env)->execute();
            }
        }
    }
}

