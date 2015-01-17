<?php

namespace Gini\Module\Gapper\Client
{
    trait CGITrait
    {
        /**
         * @brief 输出HTML
         *
         * @param $view
         * @param $data
         *
         * @return
         */
        private function showHTML($view, $data)
        {
            return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V($view, $data));
        }

        /**
         * @brief 输出JSON
         *
         * @param $data
         *
         * @return
         */
        private function showJSON($data)
        {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
        }

        /**
         * @brief 空输出
         *
         * @return
         */
        private function showNothing()
        {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        private function isLogin()
        {
            return \Gini\Gapper\Client::getLoginStep() === \Gini\Gapper\Client::STEP_DONE;
        }

        private function login()
        {
            return \Gini\Gapper\Client::goLogin();
        }

        private function logout()
        {
            \Gini\Gapper\Client::logout();
        }
    }
}
