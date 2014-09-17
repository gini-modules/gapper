<?php

namespace Gini\Controller\CGI\AJAX\Gapper;

class Client extends \Gini\Controller\CGI
{

    use \Gini\Module\Gapper\Client\RPCTrait;

    private function _showJSON($data)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
    }

    public function actionGetSources()
    {
        $current = \Gini\Gapper\Client::getLoginStep();

        $data = [];
        if ($current===\Gini\Gapper\Client::STEP_LOGIN) {
            $sources = (array)\Gini\Config::get('gapper.auth');
            $data['sources'] = [];
            foreach ($sources as $source=>$info) {
                $key = strtolower(implode('/', ['Gapper', 'Auth', $source]));
                $key = strtr($key, ['-'=>'/', '_'=>'/']);
                $data['sources'][$key] = $info;
            }
            return $this->_showJSON((string)V('gapper/client/checkauth', $data));
        }
        elseif ($current===\Gini\Gapper\Client::STEP_GROUP) {
            $groups = \Gini\Gapper\Client::getGroups();
            if (!empty($groups) && is_array($groups)) {
                $data['groups'] = $groups;
                return $this->_showJSON((string)V('gapper/client/checkgroup', $data));
            }
            \Gini\Gapper\Client::logout();
            return $this->_showJSON((string)V('error/401', $data));
        }

    }

    public function actionChoose()
    {
        $current = \Gini\Gapper\Client::getLoginStep();
        if ($current!==\Gini\Gapper\Client::STEP_GROUP) {
            return $this->_showJSON(T('Access Denied!'));
        }

        $form = $this->form('post');
        if (!isset($form['id'])) {
            return $this->_showJSON(T('Access Denied!'));
        }

        $bool = \Gini\Gapper\Client::chooseGroup($form['id']);
        if ($bool) {
            return $this->_showJSON(true);
        }
        
        return $this->_showJSON(T('Access Denied!'));
    }

}

