<?php

namespace Gini\Controller\CGI\AJAX\Gapper;

class Client extends \Gini\Controller\CGI
{
    private function _showJSON($data)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
    }

    public function actionGetSources()
    {
        $sources = (array)\Gini\Config::get('gapperauth');

        $data = [];
        foreach ($sources as $source=>$info) {
            $key = strtolower("GapperAuth" . str_replace('-', '', $source));
            $data[$key] = $info;
        }

        return $this->_showJSON((string)V('gapper/client/checkbox', ['sources'=>$data]));
    }

}

