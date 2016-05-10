<?php

/**
 * @file SSO.php
 * @brief 单点登录
 *
 * @author PiHiZi <pihizi@msn.com>
 *
 * @version 0.1.0
 * @date 2015-05-25
 */
namespace Gini\Gapper;

class SSO
{
    private $_url;
    private $_type;
    private $_params;
    private $_loop;
    public function __construct($params)
    {
        $this->_url = $params['url'];
        $this->_type = $params['type'];
        $this->_params = $params[$this->_type];
        $this->_loop = $params['loop'] ?: 5;
    }

    public function run()
    {
        $method = '_by'.ucwords($this->_type);

        return call_user_func([$this, $method], $this->_params);
    }

    private function _byCookie($opts)
    {
        $key = $opts['key'];
        $method = $opts['method'];
        $result = $opts['result'];
        $source = $opts['gapper-source'];

        $token = $_COOKIE[$key];
        if (!$token) {
            return;
        }

        $results = $this->_call($method, rawurlencode($token));
        $username = $results[$result];

        if ($source) {
            $user = (array) \Gini\Gapper\Client::getRPC()->gapper->user->getUserByIdentity($source, $username);
            $username = $user['username'];
        }

        if ($username && \Gini\Gapper\Client::getUserName() !== $username) {
            \Gini\Gapper\Client::logout();
            \Gini\Gapper\Client::loginByUserName($username);
        }
    }

    private function _call($method, $wparam = null, $lparam = null)
    {
        $results = [];

        try {
            $data['method'] = $method;
            if ($wparam !== null) {
                $data['wParam'] = $wparam;
            }
            if ($lparam !== null) {
                $data['lParam'] = $lparam;
            }

            $http = \Gini\IoC::construct('\Gini\HTTP');
            $response = $http->post($this->_url, $data);

            $xml = @simplexml_load_string($response->body);
            if (!$xml) {
                throw new \Exception('XML无法解析');
            }

            if ($xml->error) {
                throw new \Exception((string) $xml->error);
            }

            foreach ($xml->children() as $node) {
                $results[$node->getName()] = (string) $node;
            }
        } catch (\Exception $e) {
            --$this->_loop;
            if ($this->_loop > 0) {
                \usleep(200000);

                return $this->_call($method, $wparam, $lparam);
            }
        }

        return $results;
    }
}
