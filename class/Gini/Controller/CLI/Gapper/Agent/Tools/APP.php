<?php

/* vim: set ts=4 sw=4 sts=4 tw=100 */

namespace Gini\Controller\CLI\Gapper\Agent\Tools;

class APP extends \Gini\Controller\CLI
{
    public function actionAgentCurrentModule()
    {
        $arr = explode('/', APP_ID);
        $moduleName = end($arr);
        list($clientID, $clientSecret) = self::getClientInfo($moduleName);
        if (!$clientID || !$clientSecret) return;
        $moduleInfo = \Gini\Config::get("{$moduleName}.module-info");
        if (empty($moduleInfo)) return;
        $info = [
            'client_id'=> $clientID,
            'client_secret'=> $clientSecret,
            'module_name'=> $moduleName,
            'title'=> $moduleInfo['title'],
            'short_title'=> $moduleInfo['short_title'],
            'type'=> $moduleInfo['type'],
            'rate'=> $moduleInfo['rate'],
            'font_icon'=> $moduleInfo['font_icon']?:'',
        ];
        if (isset($moduleInfo['name'])) {
            $info['module_name'] = $moduleInfo['name'];
        }
        list($urlA, $urlB) = $moduleInfo['url'];
        list($iconUrlA, $iconUrlB) = $moduleInfo['icon_url'];
        $info['url'] = $urlA ?: $urlB;
        $info['icon_url'] = $iconUrlA ?: $iconUrlB;
        self::replaceLocalApp($info);
    }

    private static function getClientInfo($moduleName)
    {
        $env = APP_PATH . '/.env';
        $rows = file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $modules = [];
        foreach ($rows as &$row) {
            if (!$row || $row[0] == '#') {
                continue;
            }
            putenv($row);
        }
        $module = str_replace('-', '_', strtoupper($moduleName));
        $clientID = getenv("{$module}_GAPPER_ID");
        $clientSecret = getenv("{$module}_GAPPER_SECRET");
        return [$clientID, $clientSecret];
    }

	private static function replaceLocalApp($info)
	{
        $db = \Gini\Database::db('gapper-server-agent-db');
        $id = $db->query('select id from gapper_agent_app where client_id=:cid', null, [
            ':nid'=> $info['client_id']
        ])->value();
        if ($id) {
            $values = $db->quote([
                $id,
                $info['client_id'], $info['client_secret'],
                $info['module_name'], $info['title'], $info['short_title'],
                $info['url'], $info['icon_url'],
                $info['type'], $info['rate'],
                $info['font_icon']?:'',
            ]);
            $db->query("replace into gapper_agent_app (id,client_id,client_secret,name,title,short_title,url,icon_url,type,rate,font_icon) values({$values})");
        } else {
            $values = $db->quote([
                $info['client_id'], $info['client_secret'],
                $info['module_name'], $info['title'], $info['short_title'],
                $info['url'], $info['icon_url'],
                $info['type'], $info['rate'],
                $info['font_icon']?:'',
            ]);
            $db->query("inset into gapper_agent_app (client_id,client_secret,name,title,short_title,url,icon_url,type,rate,font_icon) values({$values})");
        }
	}

}
