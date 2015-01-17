<?php

namespace Gini\Controller\CGI
{
    class Gapper extends \Gini\Controller\CGI\Layout
    {
        private static $_JSVars = [];
        private static $_CSSes = [];

        const STRING_HEAD_CSS = 'GapperClientHeadCSS';
        const STRING_HEAD_JS = 'GapperClientHeadJS';

        public static function setJSVar($var, $value)
        {
            self::$_JSVars[$var] = $value;
        }

        public static function addCSS($css, $type = 'page')
        {
            self::$_CSSes[$type] = self::$_CSSes[$type] ?: [];
            array_push(self::$_CSSes[$type], $css);
        }

        public function __preAction($action, &$params)
        {
            $this->view = VV('gapper/client/board');
        }

        public function __postAction($action, &$params, $response)
        {
            $content = (string) $this->view;

            $headcss = V('gapper/client/headcss', ['csses' => self::$_CSSes]);
            $headjs = V('gapper/client/headjs', ['vars' => self::$_JSVars]);

            $content = str_replace(self::STRING_HEAD_CSS, $headcss, $content);
            $content = str_replace(self::STRING_HEAD_JS, $headjs, $content);

            $response = \Gini\IoC::construct('\Gini\CGI\Response\HTML', $content);

            return parent::__postAction($action, $params, $response);
        }
    }
}

namespace
{
    function VV($path, $vars = null)
    {
        $vars = is_array($vars) ? $vars : [];
        if (isset($vars['setJSVar']) || isset($vars['addCSS'])) {
            throw new Exception('setJSVar,addCSS已经被占用，请尝试其他变量名');
        }

        $view = V($path, $vars);

        $view->setJSVar = function ($var, $value) {
            \Gini\Controller\CGI\Gapper\Client::setJSVar($var, $value);
        };

        $view->addCSS = function ($css) {
            \Gini\Controller\CGI\Gapper\Client::addCSS($css);
        };

        return $view;
    }
}
