<?php

namespace Gini\Controller\CGI\AJAX\Gapper;

class Auth extends \Gini\Controller\CGI
{
    private static $_sessionKey = 'gapper.ajax.checkauth.type';
    public function actionGetForm($source)
    {
        $_SESSION[self::$_sessionKey] = strtoupper($source);
        return \Gini\CGI::request("ajax/gapper/auth/{$source}/getForm", $this->env)->execute();
    }

    public static function getSource()
    {
        return $_SESSION[self::$_sessionKey];
    }
}

