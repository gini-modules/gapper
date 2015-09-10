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

class Client extends \Gini\Controller\CGI
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
     * @brief 获取等咯过程中各个阶段的数据
     *
     * @return
     */
    public function actionSources()
    {
        $current = \Gini\Gapper\Client::getLoginStep();

        $data = [];
        switch ($current) {
        case \Gini\Gapper\Client::STEP_LOGIN:
            $conf = (array) \Gini\Config::get('gapper.auth');
            $sources = [];
            foreach ($conf as $key => $info) {
                $key = strtolower($key);
                $info['name'] = T($info['name']);
                $sources[$key] = $info;
            }

            if (count($sources) == 1) {
                return $this->redirect('ajax/gapper/auth/gapper/get-form');
            }

            return $this->_showJSON((string) V('gapper/client/checkauth', ['sources' => $sources]));

        case \Gini\Gapper\Client::STEP_GROUP:
            $groups = \Gini\Gapper\Client::getGroups();
            if ($groups && count($groups) == 1) {
                $bool = \Gini\Gapper\Client::chooseGroup(current($groups)['id']);
                if ($bool) {
                    return $this->_showJSON(true);
                }
            }

            $data['groups'] = $groups;

            return $this->_showJSON((string) V('gapper/client/checkgroup', $data));
            break;
        case \Gini\Gapper\Client::STEP_USER_401:
            \Gini\Gapper\Client::logout();
            $view = \Gini\Config::get('gapper.views')['client/error/401-user'] ?: 'gapper/client/error/401-user';

            return $this->_showJSON((string) V($view));
            break;
        case \Gini\Gapper\Client::STEP_GROUP_401:
            \Gini\Gapper\Client::logout();
            $view = \Gini\Config::get('gapper.views')['client/error/401-group'] ?: 'gapper/client/error/401-group';

            return $this->_showJSON((string) V($view));
            break;
        case \Gini\Gapper\Client::STEP_DONE:
            return $this->_showJSON(true);
            break;
        }
    }
}
