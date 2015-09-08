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
        return $this->_showHTML('gapper/auth/gapper/group_account');
    }
    public function actionGetUserAccount()
    {
        return $this->_showHTML('gapper/auth/gapper/user_account');
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
