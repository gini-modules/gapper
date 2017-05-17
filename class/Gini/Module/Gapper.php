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

            $needPrepareGapperInfo = \Gini\Config::get('gapper.need_prepare_gapper_info_in_module_setup');
            if ($needPrepareGapperInfo!==false) {
                \Gini\Gapper\Client::init();

                $me = a('gapper/user', ['username' => \Gini\Gapper\Client::getUserName()]);
                $bool = !!$me->id;

                $appInfo = \Gini\Gapper\Client::getInfo();
                if ($appInfo['type']==='group') {
                    $group = a('gapper/group', $me->id ? \Gini\Gapper\Client::getGroupID() : null);
                    $bool = $bool && !!$group->id;
                }

                if (!$bool && \Gini\Gapper\Client::getLoginStep()===\GIni\Gapper\Client::STEP_DONE) {
                    \Gini\Gapper\Client::logout();
                }
            }

            isset($_GET['locale']) and $_SESSION['locale'] = $_GET['locale'];
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
    }
}
