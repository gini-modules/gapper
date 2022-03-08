<?php

namespace Gini\Module
{
    class Gapper
    {
        public static function setup()
        {
            date_default_timezone_set(\Gini\Config::get('system.timezone') ?: 'Asia/Shanghai');

            class_exists('\Gini\Those');
            class_exists('\Gini\ThoseIndexed');

            isset($_GET['locale']) and $_SESSION['locale'] = $_GET['locale'];

            if ($_GET['from_uno'] || (strpos($_SERVER['HTTP_REFERER'], 'from_uno=true') !== false)) {
                $_SESSION['from_uno'] = true;
            }

            if ($_SESSION['from_uno']) {
                \Gini\Config::set('gapper.enable-uno-mode', true);
            }

            if (\Gini\Config::get('gapper.enable-uno-mode')) {
                _G('UNO', true);
            }
	   isset($_GET['login_by_mobile']) and $_SESSION['login_by_mobile'] = true;

            isset($_SESSION['locale']) and \Gini\Config::set('system.locale', $_SESSION['locale']);
            \Gini\I18N::setup();

            \Gini\Locale::set(LC_MONETARY, (\Gini\Config::get('system.locale') ?: 'en_US').'.UTF-8');
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

        public static function groupAutoInstallApps($e, $groupID)
        {
            if (!$groupID) return false;

            $autoInstallApps = (array) \Gini\Config::get('app.auto_install_apps_for_new_group');
            if (empty($autoInstallApps)) return false;

            $clientID = \Gini\Gapper\Client::getId();
            if (!in_array($clientID, $autoInstallApps)) return false;
            \Gini\Gapper\Client::installGroupAPPs($autoInstallApps, (int)$groupID);
        }
    }
}
