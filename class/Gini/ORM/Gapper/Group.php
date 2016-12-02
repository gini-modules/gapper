<?php

namespace Gini\ORM\Gapper;

class Group extends RObject
{
    public $name = 'string:120';
    public $creator = 'object:user';
    public $title = 'string:120';
    public $abbr = 'string:40';
    public $icon = 'string:250';

    protected function fetchRPC($criteria)
    {
        return (array)\Gini\Gapper\Client::getGroupInfo($criteria);
    }

    public function convertRPCData(array $rdata)
    {
        $data = [];
        $data['id'] = $rdata['id'];
        $data['name'] = $rdata['name'];
        $data['creator_id'] = a('user', ['username' => $rdata['creator']])->id;

        $data['title'] = $rdata['title'];
        $data['abbr'] = $rdata['abbr'];

        $data['icon'] = $rdata['icon'];

        $data['_extra'] = J(array_diff_key($rdata, array_flip(['id', 'name', 'title', 'abbr', 'creator_id', 'icon'])));

        return $data;
    }

    public function getMembers()
    {
        $start = 0;
        $per_page = 25;
        $result = [];
        while (true) {
            $members = (array) self::getRPC()->gapper->group->getMembers($this->id, null ,$start, $per_page);
            $start += $per_page;
            if (!count($members)) break;
            $result = $result + $members;
        }
        return $result;
    }

    public function getApps()
    {
        if (!$this->id) return [];
        return (array) \Gini\Gapper\Client::getGroupApps($this->id);
    }

    public function icon($size = null)
    {
        $url = $this->icon;
        if (!$url) {
            return;
        }

        $scheme = parse_url($url)['scheme'];
        if ($scheme != 'http') {
            return $url;
        }

        return \Gini\ImageCache::makeURL($url, $size);
    }
}
