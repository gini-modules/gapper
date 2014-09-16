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
    }
}
