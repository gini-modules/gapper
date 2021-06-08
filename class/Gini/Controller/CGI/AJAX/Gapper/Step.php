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
    private static $_defaultAuthType='gapper';
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
        $source = Auth::getSource();
        if (!$source) {
            $source = \Gini\Config::get('gapper.auth-default-login')?:self::$_defaultAuthType;
        }
        $result = $this->_trySourceMethod('login', $source);
        if ($result) return $result;
        return $this->login();
    }

    public function login()
    {
        $conf = (array) \Gini\Config::get('gapper.auth');
        if (strpos($_SERVER['HTTP_REFERER'], 'fromUno=true') !== false) {
            \Gini\Config::set('gapper.enable-uno-mode', true);
        }
        $enable_uno_mode = \Gini\Config::get('gapper.enable-uno-mode');
        $sources = [];
        if ($enable_uno_mode) {
            return $this->_showHTML('gapper/client/checkauth', [
                'sources' => $sources,
                'enable_uno_mode'=>$enable_uno_mode,
                'uno_conf' => \Gini\Config::get('gapper.uno')
            ]);
        }
        foreach ($conf as $key => $info) {
            $key = strtolower($key);
            if ($info['show']===false) continue;
            $info['name'] = $info['name'];
            $keyData = \Gini\CGI::request("ajax/gapper/auth/{$key}/get-form", $this->env)->execute();
            $keyData = @json_decode($keyData,true);
            if ($keyData && is_array($keyData) && $keyData['redirect']) {
                $info['url'] = $keyData['redirect'];
            }
            $sources[$key] = $info;
        }

        if (count($sources) == 1) {
            $ck = current(array_keys($sources));
            if ($ck==self::$_defaultAuthType) {
                return $this->_showHTML('gapper/auth/gapper/login', [
                    'info' => (object) \Gini\Config::get('gapper.auth')['gapper'],
                    'hasMultiLogType' => !!(count(array_keys($conf)) > 1)
                ]);
            }
            return \Gini\CGI::request("ajax/gapper/auth/get-form/{$ck}", $this->env)->execute();
        }

        return $this->_showHTML('gapper/client/checkauth', ['sources' => $sources]);
    }

    public function actionGroup()
    {
        $source = Auth::getSource();
        if (!$source) {
            $source = \Gini\Config::get('gapper.auth-default-group')?:self::$_defaultAuthType;
        }
        $result = $this->_trySourceMethod('group', $source);
        if ($result) return $result;
        return $this->group();
    }

    public function group()
    {
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
        $source = Auth::getSource();
        if (!$source) {
            $source = \Gini\Config::get('gapper.auth-default-user-401')?:self::$_defaultAuthType;
        }
        $result = $this->_trySourceMethod('user401', $source);
        if ($result) return $result;
        return $this->user401();
    }

    public function user401()
    {
        $view = \Gini\Config::get('gapper.views')['client/error/401-user'] ?: 'gapper/client/error/401-user';

        return $this->_showHTML($view);
    }

    public function actionGroup401()
    {
        $source = Auth::getSource();
        if (!$source) {
            $source = \Gini\Config::get('gapper.auth-default-group-401')?:self::$_defaultAuthType;
        }
        $result = $this->_trySourceMethod('group401', $source);
        if ($result) return $result;
        return $this->group401();
    }

    public function group401()
    {
        $view = \Gini\Config::get('gapper.views')['client/error/401-group'] ?: 'gapper/client/error/401-group';

        return $this->_showHTML($view);
    }

    public function actionDone()
    {
        $source = Auth::getSource();
        if (!$source) {
            $source = \Gini\Config::get('gapper.auth-default-done')?:self::$_defaultAuthType;
        }
        $result = $this->_trySourceMethod('groupDone', $source);
        if ($result) return $result;
        return $this->done();
    }

    public function done()
    {
        $referer = parse_url(\Gini\URI::url($_SERVER['HTTP_REFERER']));
        $query = $referer['query'];
        parse_str($query, $params);

        $redirectURL = \Gini\URI::url($params['redirect']?:'/', [
            'gapper-token'=> '',
            'gapper-group'=> ''
        ]);
        return $this->_showJSON([
            'redirect'=> $redirectURL,
            'message'=> (string)V('gapper/client/redirect')
        ]);
    }

    private function _trySourceMethod($method, $source=null)
    {
        if ($source) {
            $className = "\\Gini\\Controller\\CGI\\AJAX\\Gapper\\Auth\\{$source}\\Step\\{$method}";
            if (class_exists($className)) {
                return \Gini\CGI::request("ajax/gapper/auth/{$source}/step/{$method}", $this->env)->execute();
            }
        }
    }
}
