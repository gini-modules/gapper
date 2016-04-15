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
            return \Gini\CGI::request('ajax/gapper/step/login', $this->env)->execute();
        case \Gini\Gapper\Client::STEP_GROUP:
            return \Gini\CGI::request('ajax/gapper/step/group', $this->env)->execute();
        case \Gini\Gapper\Client::STEP_USER_401:
            return \Gini\CGI::request('ajax/gapper/step/user401', $this->env)->execute();
        case \Gini\Gapper\Client::STEP_GROUP_401:
            return \Gini\CGI::request('ajax/gapper/step/group401', $this->env)->execute();
        case \Gini\Gapper\Client::STEP_DONE:
            return \Gini\CGI::request('ajax/gapper/step/done', $this->env)->execute();
        }
    }
}
