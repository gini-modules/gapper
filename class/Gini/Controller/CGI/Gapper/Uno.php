<?php

namespace Gini\Controller\CGI\Gapper;

class Uno extends \Gini\Controller\CGI\Layout\Gapper
{
	const STATUS_PENDING = 0;
	const STATUS_REJECT = 2;

	public static $approval_types = [
		'join_group'   => '加入',
		'create_group' => '创建',
	];
	public function actionGroup401()
	{
		$me = _G('ME');
		if (\Gini\Gapper\Client::getLoginStep() != \Gini\Gapper\Client::STEP_GROUP_401) {
			$this->redirect('error/401');
		}
		# 有申请待审
		# 有申请被拒绝
		# 无申请

        $rest = new \Gini\HTTP();

        $config      = \Gini\Config::get('api.uniadmin-access-agent-config');
        $gapper_conf = \Gini\Config::get('gapper.rpc');
        $url               = $config['url'];
        $app_client_id     = $gapper_conf['client_id'];
        $app_client_secret = $gapper_conf['client_secret'];

        $result = $rest->post($url.'/v1/auth/app-token', [
            'client_id'     => $app_client_id,
            'client_secret' => $app_client_secret
        ]);
        $re = json_decode($result, true);
        $access_token = $re['access_token'];
        if (!$access_token) {
        	$this->redirect('error/401');
        }
        $rest->header('X-Gapper-OAuth-Token', $access_token);
        /*
		status 0=待处理 1=已通过 2=已拒绝
		order_by  按字段排序，-代表倒序
        */


        $criteria = [
        	'user_id' => $me->id,
        	'status' => [self::STATUS_PENDING,self::STATUS_REJECT],
        	'type$' => ['join_group','create_group']
        ];
        $resp                 = $rest->get($url . '/v1/approvals', $criteria);
        $res                  = @json_decode($resp->body, true);
        $items                = $res['items'];
        $count                = $res['count'];
        $enable_operate       = false;
        $has_pending_approval = false;
        $has_reject_approval  = false;
        $pending_groups = [];
        $reject_groups = [];
        $type = '';
        if ($count == 0) {
        	# 无申请
        	$title = H(T('未找到您的课题组信息, 请先加入课题组'));
        	$descriptions[] = H(T('系统没有找到您的课题组信息， 您需要先申请加入课题组或者创建课题组（仅教师）'));
        	$icon = 'loading';
        	$enable_operate = true;
        }
        else {
        	foreach ($items as $item) {
        		if ($item['status'] == self::STATUS_PENDING) {
        			$has_pending_approval = true;
        			$pending_groups[] = [
        				'name' => $item['data']['name'],
        				'type' => self::$approval_types[$item['type']],
        			];
        		}
        		else if ($item['status'] == self::STATUS_REJECT) {
        			$has_reject_approval  = true;
        			$reject_groups[] = [
        				'name' => $item['data']['name'],
        				'note' => $item['note'],
        				'type' => self::$approval_types[$item['type']],
        			];
        		}
        	}
        }

        if ($has_pending_approval) {
        	$title = H(T('入组审核中, 请耐心等待'));
        	foreach ($pending_groups as $pending_group) {
				$descriptions[] = H(T('您已申请%type %group, 等待上级审核中, 请耐心等待',[
					'%type' => $pending_group['type'],
					'%groups'=>$pending_group['name']]
				));
        	}
        	$icon = 'pending';
        	$enable_operate = false;
        }
        elseif ($has_reject_approval) {
        	$title = H(T('入组审核中, 请耐心等待'));
        	foreach ($reject_groups as $reject_group) {
				$descriptions[] = H(T('您申请%type %group 被拒绝， 拒绝原因： %note',[
					'%type' => $reject_group['type'],
					'%group'=>$reject_group['name'] ,
					'%note'=>$reject_group['note']]));
        	}
        	$icon = 'fail';
        	$enable_operate = true;
        }

        $vars = [
        	'icon' => $icon,
        	'title' => $title,
        	'descriptions' => $descriptions,
        	'enable_operate' => $enable_operate
        ];

        $this->view->body = V('uno/group401', $vars);
	}
}
