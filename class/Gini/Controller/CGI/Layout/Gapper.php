<?php

namespace Gini\Controller\CGI\Layout;

abstract class Gapper extends \Gini\Controller\CGI\Layout
{
    protected static $layout_name = 'layout/gapper';

    public function show404()
    {
        $this->view->body = V('error/404');
    }

    public function show401()
    {
        $this->view->body = V('error/401');
    }

    public function __preAction($action, &$params)
    {
        return parent::__preAction($action, $params);
    }

    public function __postAction($action, &$params, $response)
    {
        return parent::__postAction($action, $params, $response);
    }
}
