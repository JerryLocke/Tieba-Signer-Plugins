<?php
if (!defined('IN_KKFRAME')) exit('Access Denied!');
class plugin_zw_client_api extends Plugin {
	var $description = '安卓客户端 API，使用安卓客户端需要本插件的支持';
	var $version = '1.0.0';

	function handleAction() {
		global $uid, $formhash;
		$status = -1;
		$msg = '未登录！';
		$data = array('time' => time());
		if ($_SERVER['HTTP_USER_AGENT'] != 'Android Client For Tieba Signer') {
			exit(json_encode(array('status' => -2, 'msg' => '非法操作', 'data' => $data)));
		} else {
			if ($_GET['a'] == 'api_info') {
				$status = 0;
				$data = array('version' => '1.0.0', 'site' => $_SERVER["HTTP_HOST"]);
			} elseif ($_GET['a'] == 'do_login') {
				if (!empty($_POST['username']) && !empty($_POST['password'])) {
					$username = daddslashes($_POST['username']);
					$un = strtolower($username);
					if (strlen($username) > 24) {
						$msg = '用户名过长，请修改';
						$status = 3;
					} else {
						$user = DB :: fetch_first("SELECT * FROM member WHERE username='{$username}'");
						$verified = Widget_Password :: verify($user, $_POST['password']);
						if ($verified) {
							$login_exp = TIMESTAMP + 3600;
							do_login($user['uid']);
							$status = 0;
							$msg = "欢迎回来，{$user['username']}！";
							$data = array('uid' => $user['uid'], 'username' => $user['username'], 'email' => $user['email'], 'formhash' => substr(md5(substr(TIMESTAMP, 0, -7) . $user['username'] . $user['uid'] . ENCRYPT_KEY . ROOT), 8, 8));
						} else {
							$status = 2;
							$msg = "对不起，您的用户名或密码错误，无法登录";
						} 
					} 
				} else {
					$status = 1;
					$msg = '用户名或密码不得为空!';
				} 
			} elseif ($_GET['a'] == 'check_login') {
				if ($uid) $status = 0;
				if ($uid) $msg = '您已登录';
			} elseif ($formhash != $_GET['formhash']) {
				$status = -2;
				$msg = '非法操作';
			} elseif ($uid) {
				$status = 0;
				$msg = "";
				require_once ROOT . './plugins/zw_client_api/BaiduUtil.php';
				$binded_baidu = true;
				$cookie = get_cookie($uid);
				if (empty($cookie)) {
					$binded_baidu = false;
				} else {
					try {
						$baiduUtil = new BaiduUtil(get_cookie($uid));
					} 
					catch(Exception $e) {
						if ($e -> getCode() == -99) $binded_baidu = false;
					} 
				} 
				switch ($_GET['a']) {
					case 'baidu_info':
						if ($binded_baidu) {
							$msg = '百度账号信息';
							try {
								$baidu_account_info = $baiduUtil -> fetchClientUserInfo();
								$baidu_account_tieba_list = $baiduUtil -> fetchClientLikedForumList();
								$baidu_account_follow_list = $baiduUtil -> fetchFollowList(4);
								$baidu_account_fans_list = $baiduUtil -> fetchFansList(4);
								$data = array('id' => $baidu_account_info['data']['id'], 'username' => $baidu_account_info['data']['un'], 'avatar' => $baidu_account_info['data']['head_photo_h'], 'sex' => $baidu_account_info['data']['sex'], 'tb_age' => $baidu_account_info['data']['tb_age'], 'fans_num' => $baidu_account_info['data']['fans_num'], 'follow_num' => $baidu_account_info['data']['concern_num'], 'tb_num' => $baidu_account_info['data']['like_forum_num'], 'intro' => $baidu_account_info['data']['intro']?$baidu_account_info['data']['intro']:'这个家伙很懒，什么也没有留下', 'tiebas' => $baidu_account_tieba_list['data'] ? $baidu_account_tieba_list['data'] : array(), 'follow' => $baidu_account_follow_list['data'], 'fans' => $baidu_account_fans_list['data'],);
							} 
							catch(Exception $e) {
								$status = "3";
								$msg = '助手站点错误：' . $e -> getMessage();
							} 
						} else {
							$status = 1;
							$msg = "未绑定百度账号";
						} 
						break;
					case 'unbind_baidu':
						DB :: query("UPDATE member_setting SET cookie='' WHERE uid='{$uid}'");
						DB :: query("DELETE FROM my_tieba WHERE uid='{$uid}'");
						DB :: query("DELETE FROM sign_log WHERE uid='{$uid}'");
						$msg = "已经解除百度账号绑定，您可以稍后重新进行绑定";
						break;
					case 'sign_log':
						$msg = '获取成功';
						$date = intval($_GET['date']);
						$data['date'] = $date;
						$data['log'] = array();
						$query = DB :: query("SELECT * FROM sign_log l LEFT JOIN my_tieba t ON t.tid=l.tid WHERE l.uid='{$uid}' AND l.date='{$date}'");
						while ($result = DB :: fetch($query)) {
							$data['log'][] = $result;
						} 
						$data['count'] = count($data['log']);
						$previous_date = DB :: result_first("SELECT date FROM sign_log WHERE uid='{$uid}' AND date<'{$date}' ORDER BY date DESC LIMIT 0,1");
						$next_date = DB :: result_first("SELECT date FROM sign_log WHERE uid='{$uid}' AND date>'{$date}' ORDER BY date ASC LIMIT 0,1");
						$data['previous_date'] = $previous_date ? $previous_date : '0';
						$data['next_date'] = $next_date ? $next_date : '0';
						break;
					case 'cloud_info':
						$msg = '获取成功';
						$data['sid'] = cloud :: id();
						break;
					case 'plugin_info':
						$msg = '获取成功';
						$plugin_info = CACHE :: get('plugins');
						$data['plugins'] = array();
						$plugin_supported = array('zw_custom_page' => array('name' => '自定义页面', 'author' => 'JerryLocke'), 'zw_blockid' => array('name' => '循环封禁', 'author' => 'JerryLocke'), 'x_tdou' => array('name' => 'T豆', 'author' => '星弦雪'), 'xxx_post' => array('name' => '客户端回帖', 'author' => '星弦雪'), 'xxx_meizi' => array('name' => '妹纸认证', 'author' => '星弦雪'));
						foreach($plugin_info as $plugin) {
							if (isset($plugin_supported[$plugin['id']])) $data['plugins'][] = $plugin + $plugin_supported[$plugin['id']];
						} 
						$data['count'] = count($data['plugins']);
						break;
				} 
			} 
			echo json_encode(array('status' => $status, 'msg' => $msg, 'data' => $data));
		} 
	} 
} 
