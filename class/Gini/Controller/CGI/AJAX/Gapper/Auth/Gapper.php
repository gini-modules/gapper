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
    use \Gini\Module\Gapper\Client\RPCTrait;

    private function _showJSON($data)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
    }

    private function _showHTML($view, array $data = [])
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V($view, $data));
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
        try {
            $bool = self::getRPC()->gapper->user->verify($username, $password);
        } catch (\Exception $e) {
        }

        if ($bool) {
            $result = \Gini\Gapper\Client::loginByUserName($username);
            if ($result) {
                // by pihizi
                // 登录成功直接返回TRUE, 又有有前端页面控制如何进行接下来的行为
                // return $this->_showJSON(true);
                // 我现在对他做如下修改:
                //   登录成功，直接当前页面选组
                $bool = \Gini\CGI::request('ajax/gapper/client/sources', $this->env)->execute()->content();
                if ($bool!==true && $bool) {
                    return $this->_showJSON([
                        'type'=> 'modal',
                        'message'=> (string)$bool
                    ]);
                }
                return $this->_showJSON(true);
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
            return $this->_showJSON(true);
        }

        return $this->_showJSON(T('Access Denied!'));
    }
}
