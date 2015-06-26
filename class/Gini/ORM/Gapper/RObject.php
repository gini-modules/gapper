<?php

namespace Gini\ORM\Gapper;

/**
 * Robject 是用于数据获取的特殊类：用于数据模型对象远程rpc获取相应信息的底层支持类.
 **/
abstract class RObject extends \Gini\ORM\Object
{
    //缓存时间
    protected $cacheTimeout = 5;

    /**
     * 获取默认指定API路径的RPC对象
     *
     * @return new RPC
     **/
    protected static $_RPC = null;
    protected static function getRPC()
    {
        if (!self::$_RPC) {
            $conf = \Gini\Config::get('gapper.rpc');
            try {
                $rpc = \Gini\IoC::construct('\Gini\RPC', $conf['url']);
                $rpc->gapper->app->authorize($conf['client_id'], $conf['client_secret']);
                self::$_RPC = $rpc;
            } catch (RPC\Exception $e) {
            }
        }

        return self::$_RPC;
    }

    /**
     * 按照配置设定的path 和 method 来进行RPC远程数据抓取.
     *
     * @return mixed
     **/
    protected function fetchRPC($id)
    {
        return false;
    }

    public function db()
    {
        return false;
    }

    public function fetch($force = false)
    {
        if ($force || $this->_db_time == 0) {
            if (is_array($this->_criteria) && count($this->_criteria) > 0) {
                $criteria = $this->normalizeCriteria($this->_criteria);

                if (isset($criteria['id'])) {
                    $id = $criteria['id'];
                    $key = $this->name().'#'.$id;
                    $cacher = \Gini\Cache::of('orm');
                    $data = $cacher->get($key);
                    if (is_array($data)) {
                        \Gini\Logger::of('orm')->debug("cache hits on $key");
                    } else {
                        \Gini\Logger::of('orm')->debug("cache missed on $key");
                        $rdata = $this->fetchRPC($id);
                        if (is_array($rdata) && count($rdata) > 0) {
                            $data = $this->convertRPCData($rdata);
                            // set ttl to cacheTimeout sec
                            $cacher->set($key, $data, $this->cacheTimeout);
                        }
                    }

                    // 确认数据有效再进行id赋值
                    if (is_array($data) && count($data) > 0) {
                        $data['id'] = $id;
                    }
                }
            }

            $this->setData((array) $data);
        }
    }

    public function delete()
    {
        return false;
    }

    public function save()
    {
        return false;
    }
}
