<?php

/* vim: set ts=4 sw=4 sts=4 tw=100 */

namespace Gini\Controller\CLI\Gapper\Agent\Tools;

class Server extends \Gini\Controller\CLI
{
    public function actionSyncAppInfo()
    {
        $apps = array_merge(
            self::getMallOldIDSecrets(\Gini\Config::get('app.node')),
            self::getMallStatIDSecrets(),
            self::getNodeAppIDSecrets()
        );
        foreach ($apps as $clientID=>$clientSecret) {
            $info = self::getAppInfo($clientID);
            if (empty($info)) continue;
            $info['client_id'] = $clientID;
            $info['client_secret'] = $clientSecret;
			self::replaceLocalApp($info);
        }
    }

    private static function getMallOldIDSecrets($node)
    {
        $file = APP_PATH . '/../../../mall-old/sites/' . $node . '/config/mall.php';
        if (!file_exists($file)) return [];
        $rows = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $gapperRows = [];
        $gs = false;
        foreach ($rows as $row) {
            if (!$row || "{$row[0]}{$row[1]}"=='//') continue;
            if (!$gs) {
                if (preg_match('/^\s*\$config\[\'gapper\'\]/', $row)) {
                    $gs = true;
                }
            } else {
                if (preg_match('/^\s*\'client_id\'\s*=>\s*\'([a-z\=\_\-0-9]+)\'/', $row, $matches)) {
                    $clientID = $matches[1];
                }
                if (preg_match('/^\s*\'client_secret\'\s*=>\s*\'([a-z\=\_\-0-9]+)\'/', $row, $matches)) {
                    $clientSecret = $matches[1];
                }
                if (preg_match('/^\s*\];/', $row)) break;
            }
        }
        if (!$clientID || !$clientSecret) return [];
        return [$clientID=>$clientSecret];
    }

    private static function getMallStatIDSecrets()
    {
        $env = $_SERVER['GINI_ENV'];
        if ($env) {
            $file = APP_PATH . '/../../mall-stat/raw/config/@'.$env.'/gapper.yml';
        } else {
            $file = APP_PATH . '/../../mall-stat/raw/config/gapper.yml';
        }
        if (!file_exists($file)) return [];
        $config = (array) yaml_parse(file_get_contents($file));
        $clientID = $config['rpc']['client_id'];
        $clientSecret = $config['rpc']['client_secret'];
        if (!$clientID || !$clientSecret) return [];
        return [$clientID=>$clientSecret];
    }

    private static function getNodeAppIDSecrets()
    {
        $env = APP_PATH . '/.env';
        $rows = file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $modules = [];
        foreach ($rows as &$row) {
            if (!$row || $row[0] == '#') {
                continue;
            }
            list($key,) = explode('=', $row);
            putenv($row);
            if (false!==strpos($key, 'GAPPER_ID')) {
                $modules[] = str_replace('_GAPPER_ID', '', $key);
            }
        }

        $apps = [];
        foreach ($modules as $module) {
            $clientID = getenv("{$module}_GAPPER_ID");
            $clientSecret = getenv("{$module}_GAPPER_SECRET");
            if (!$clientID || !$clientSecret) continue;
            if (isset($apps[$clientID])) continue;
            $apps[$clientID] = $clientSecret;
        }
        return $apps;
    }

	private static function replaceLocalApp($info)
	{
        $db = \Gini\Database::db('gapper-server-agent-db');
        $values = $db->quote([
            $info['id'],
            $info['client_id'], $info['client_secret'],
            $info['module_name'], $info['title'], $info['short_title'],
            $info['url'], $info['icon_url'],
            $info['type'], $info['rate'],
            $info['font_icon']?:'',
        ]);
        $db->query("replace into gapper_agent_app (id,client_id,client_secret,name,title,short_title,url,icon_url,type,rate,font_icon) values({$values})");
	}

    private static function getAppInfo($clientID)
    {
        $rpc = \Gini\Gapper\Client::getRPC();
        $info = $rpc->gapper->app->getInfo($clientID);
        if (empty(array_diff([
            'module_name', 'type', 'rate', 'font_icon'
        ], array_keys($info)))) return $info;
    }
}
