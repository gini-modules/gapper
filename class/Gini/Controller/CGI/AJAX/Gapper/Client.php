<?php

namespace Gini\Controller\CGI\AJAX\Gapper;

class Client extends \Gini\Controller\CGI
{

    use \Gini\Module\Gapper\Client\RPCTrait;

    private function _showJSON($data)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $data);
    }

    private function _showHTML($view, array $data=[])
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V($view, $data));
    }

    public function actionGetSources()
    {
        $current = \Gini\Gapper\Client::getLoginStep();

        $data = [];
        switch ($current) {
        case \Gini\Gapper\Client::STEP_LOGIN:
            $sources = (array) \Gini\Config::get('gapper.auth');

            $data['sources'] = [];
            foreach ($sources as $source=>$info) {
                $key = strtolower(implode('/', ['Gapper', 'Auth', $source]));
                $key = strtr($key, ['-'=>'/', '_'=>'/']);
                $data['sources'][$key] = $info;
            }

            $data['sources']['gapper/client'] = [
                'icon'=> '/assets/img/gapper-auth-gapper/logo.png',
                'name'=> T('Gapper')
            ];

            if (count($data['sources'])==1) {
                return $this->actionGetForm();
            }

            return $this->_showJSON((string) V('gapper/client/checkauth', $data));
            break;
        case \Gini\Gapper\Client::STEP_GROUP:
            $groups = \Gini\Gapper\Client::getGroups();
            if ($groups && count($groups)==1) {
                $bool = \Gini\Gapper\Client::chooseGroup(current($groups)['id']);
                if ($bool) {
                    return $this->_showJSON(true);
                }
            }

            $data['groups'] = $groups;
            return $this->_showJSON((string) V('gapper/client/checkgroup', $data));
            break;
        case \Gini\Gapper\Client::STEP_USER_401:
            \Gini\Gapper\Client::logout();
            $view = \Gini\Config::get('gapper.views')['client/error/401-user'] ?: 'gapper/client/error/401-user';

            return $this->_showJSON((string) V($view));
            break;
        case \Gini\Gapper\Client::STEP_GROUP_401:
            \Gini\Gapper\Client::logout();
            $view = \Gini\Config::get('gapper.views')['client/error/401-group'] ?: 'gapper/client/error/401-group';

            return $this->_showJSON((string) V($view));
            break;
        case Gini\Gapper\Client::STEP_DONE:
            return $this->_showJSON(true);
            break;
        }
    }

    public function actionGetForm()
    {
        $info = (object) [
            'icon'=> '/assets/img/gapper-auth-gapper/logo.png',
            'name'=> T('Gapper')
        ];

        return $this->_showHTML('gapper/auth/gapper/login', [
            'info'=> $info
        ]);
    }

    public function actionLogin()
    {
        if (\Gini\Gapper\Client::getLoginStep()===\Gini\Gapper\Client::STEP_DONE) {
            return $this->_showJSON(true);
        }

        $form = $this->form('post');
        $username = $form['username'];
        $password = $form['password'];
        $bool = self::getRPC()->user->verify($username, $password);

        if ($bool) {
            $result = \Gini\Gapper\Client::loginByUserName($username);
            if ($result) {
                return $this->_showJSON(true);
            }
        }

        return $this->_showJSON(T('Login failed! Please try again.'));
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
