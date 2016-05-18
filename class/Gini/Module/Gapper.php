<?php

namespace Gini\Module
{
    class Gapper
    {
        public static function setup()
        {
            /*
            date_default_timezone_set(\Gini\Config::get('system.timezone') ?: 'Asia/Shanghai');

            _G('HEADCSS', '<GINI-HEADCSS>');
            _G('HEADJS', '<GINI-HEADJS>');

            if (isset($_GET['locale'])) {
                $_SESSION['locale'] = $_GET['locale'];
            }
            if ($_SESSION['locale']) {
                \Gini\Config::set('system.locale', $_SESSION['locale']);
            }
            \Gini\I18N::setup();
             */
        }

        public static function diagnose()
        {
            $errors = [];

            $serverHome = \Gini\Config::get('gapper.server_home');
            if (!$serverHome) {
                $errors[] = '如果不设置gapper.server_home，默认server_home的值为http://gapper.in';
            }

            $homeAPPClientID = \Gini\Config::get('gapper.home_app_client');
            if (!$homeAPPClientID) {
                $errors[] = '如果不设置gapper.home_app_client，默认首页将跳转到gapper.server_home定义的主页';
            }

            if (!empty($errors)) {
                return $errors;
            }
        }
    }
}
