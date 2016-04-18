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
        $groups = \Gini\Gapper\Client::getGroups();
        if ($groups && count($groups) == 1) {
            $bool = \Gini\Gapper\Client::chooseGroup(current($groups)['id']);
            if ($bool) {
                return $this->_showJSON(true);
            }
        }

        $data['groups'] = $groups;

        return $this->_showHTML('gapper/client/checkgroup', $data);
    }

    public function actionUser401() 
    {
        \Gini\Gapper\Client::logout();
        $view = \Gini\Config::get('gapper.views')['client/error/401-user'] ?: 'gapper/client/error/401-user';

        return $this->_showHTML($view);
    }

    public function actionGroup401()
    {
        \Gini\Gapper\Client::logout();
        $view = \Gini\Config::get('gapper.views')['client/error/401-group'] ?: 'gapper/client/error/401-group';

        return $this->_showHTML($view);
    }

    public function actionDone()
    {
        return $this->_showJSON(true);
    }
}

