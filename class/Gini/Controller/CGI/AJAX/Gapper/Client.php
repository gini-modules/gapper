<?php

namespace Gini\Controller\CGI\AJAX\Gapper;

class Client extends \Gini\Controller\CGI
{
    private static $_RPC = [];
    public static function getRPC($type='gapper')
    {
        if (!self::$_RPC[$type]) {
            try {
                $api = \Gini\Config::get($type . '.url');
                $client_id = \Gini\Config::get($type . '.client_id');
                $client_secret = \Gini\Config::get($type . '.client_secret');
                $rpc = \Gini\IoC::construct('\Gini\RPC', $api, $type);
                $bool = $rpc->authorize($client_id, $client_secret);
                if (!$bool) {
                    throw new \Exception('Your APP was not registered in gapper server!');
                }
            } catch (\Gini\RPC\Exception $e) {
            }

            self::$_RPC[$type] = $rpc;
        }

        return self::$_RPC[$type];
    }

    private function _showJSON($data)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
    }

    public function actionGetSources()
    {
        $current = \Gini\Gapper\Client::getLoginStep();

        $data = [];
        if ($current===\Gini\Gapper\Client::STEP_LOGIN) {
            $sources = (array)\Gini\Config::get('gapperauth');
            $data['sources'] = [];
            foreach ($sources as $source=>$info) {
                $key = strtolower("GapperAuth" . str_replace('-', '', $source));
                $data['sources'][$key] = $info;
            }
            return $this->_showJSON((string)V('gapper/client/checkauth', $data));
        }
        elseif ($current===\Gini\Gapper\Client::STEP_GROUP) {
            $data['groups'] = self::getRPC()->user->getGroups(\Gini\Gapper\Client::getUserName());
            return $this->_showJSON((string)V('gapper/client/checkgroup', $data));
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

