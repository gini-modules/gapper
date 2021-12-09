<?php

namespace Gini\Controller\CGI\Gapper;

class Client extends \Gini\Controller\CGI\Gapper
{
    protected static $redirect_session_key = 'APP#LOGIN#GOTO';
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

    public function actionCreateCaptcha()
    {
        //开启session
        session_start();
        //创建一个大小为 100*30 的验证码
        $image = imagecreatetruecolor(100, 30);
        $bgcolor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgcolor);

        $captch_code = '';
        for ($i = 0; $i < 4; $i++) {
            $fontsize = 6;
            $fontcolor = imagecolorallocate($image, rand(0, 120), rand(0, 120), rand(0, 120));
            $data = 'abcdefghijkmnpqrstuvwxy3456789';
            $fontcontent = substr($data, rand(0, strlen($data) - 1), 1);
            $captch_code .= $fontcontent;
            $x = ($i * 100 / 4) + rand(5, 10);
            $y = rand(5, 10);
            imagestring($image, $fontsize, $x, $y, $fontcontent, $fontcolor);
        }
        //就生成的验证码保存到session
        $_SESSION['gapper_authcode'] = $captch_code;

        //在图片上增加点干扰元素
        for ($i = 0; $i < 200; $i++) {
            $pointcolor = imagecolorallocate($image, rand(50, 200), rand(50, 200), rand(50, 200));
            imagesetpixel($image, rand(1, 99), rand(1, 29), $pointcolor);
        }

        //在图片上增加线干扰元素
        for ($i = 0; $i < 3; $i++) {
            $linecolor = imagecolorallocate($image, rand(80, 220), rand(80, 220), rand(80, 220));
            imageline($image, rand(1, 99), rand(1, 29), rand(1, 99), rand(1, 29), $linecolor);
        }
        //设置头
        header('content-type:image/png');
        imagepng($image);
        imagedestroy($image);
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
        if (!$client_id) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $app = \Gini\Gapper\Client::getInfo($client_id);
        if (!$app['id'] || !$app['url']) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        if (\Gini\Gapper\Client::getLoginStep() !== \Gini\Gapper\Client::STEP_DONE) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        $redirect = $_GET['redirect'];

        $url = $app['url'];
        $confs = \Gini\Config::get('gapper.proxy');
        foreach ((array)$confs as $conf) {
            if ($url==$conf['url']) {
                $url = $conf['proxy'] ?: $url;
                break;
            }
        }

        $currentURL = 'http://' . $_SERVER['HTTP_HOST'];

        if ($this->_checkUrl($url, $redirect)) {
            $url = $redirect;
        }

        $toURI = parse_url($url);
        $toDomain = $toURI['host'];
        $fromDomain= $_SERVER['SERVER_NAME'];
        $isSelf = !!($fromDomain==$toDomain);

        $query = [];
        // mall-old采用的不是gini框架的共享session机制。需要独立登录
        // 如果从域名和path兼容的角度，还是要token的
        // if ($app['module_name']=='mall-old' || !$this->_checkUrl($currentURL, $url)) {
        $isOP = !!($app['module_name'] == 'mall-old');
        if (!$isSelf || $isOP) {
                $username = \Gini\Gapper\Client::getUserName();
                if ($username) {
                        $token = \Gini\Gapper\Client::getLoginToken($client_id, $username);
                        if ($token) {
                                $query['gapper-token'] = $token;
                        }
                }
        }
        // }

        if (($app['type'] == 'group') && $group_id && (!$isSelf || $group_id!=_G('GROUP')->id)) $query['gapper-group'] = $group_id;

        $url = \Gini\URI::url($url, $query);

        return $this->redirect($url);
    }

    public function actionLogin()
    {
        $redirect = $_GET['redirect'];
        if (\Gini\Gapper\Client::getLoginStep() === \Gini\Gapper\Client::STEP_DONE) {
            $host = $_SERVER['HTTP_HOST'];
            if (0===strpos($redirect, '/')) {
                $appInfo = \Gini\Gapper\Client::getInfo();
                $redirect = \Gini\URI::url($appInfo['url'].$redirect);
            }
            $redirect = $this->_checkUrl("http://{$host}/", $redirect) ? $redirect : '/';

            return $this->redirect($redirect);
        }

        if ($redirect) {
            $_SESSION[self::$redirect_session_key] = $redirect;
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
