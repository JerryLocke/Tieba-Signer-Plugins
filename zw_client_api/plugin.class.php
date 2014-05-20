<?php
if (!defined('IN_KKFRAME')) exit('Access Denied!');
class plugin_zw_client_api extends Plugin {
	var $description = '安卓客户端 API，使用安卓客户端需要本插件的支持';
	var $version = '1.0.0';

	function handleAction() {
		global $uid;
		$status = -1;
		$msg = "未登录！";
		$data = array('time' => time()); 
		// NOTICE : Just For Test;
		/**
		 * if ($_SERVER['HTTP_USER_AGENT'] != 'Android Client For Tieba Signer') {
		 * exit(json_encode(array('status' => -1, 'msg' => '非法操作', 'data' => '')));
		 * } else
		 */
		if ($_GET['a'] == 'api_info') {
			$status = 0;
			$data = array('version' => '1.0.0', 'site' => $_SERVER["HTTP_HOST"]);
		} elseif ($_GET['a'] == 'do_login') {
			if ($_POST['username'] && $_POST['password']) {
				$username = daddslashes($_POST['username']);
				$password = md5(ENCRYPT_KEY . md5($_POST['password']) . ENCRYPT_KEY);
				$un = strtolower($username);
				if (strlen($username) > 24) showmessage('用户名过长，请修改', dreferer(), 5);
				$user = DB :: fetch_first("SELECT * FROM member WHERE username='{$username}' AND password='{$password}'");
				if ($user) {
					$status = 0;
					$login_exp = TIMESTAMP + 3600;
					do_login($user['uid']);
					$msg = "欢迎回来，{$user['username']}！";
					$data = array('username' => $user['username'], 'email' => $user['email']);
				} else {
					$status = 2;
					$msg = "用户名或密码错误，登录失败";
				} 
			} else {
				$status = 1;
				$msg = "用户名或密码不得为空!";
			} 
		} elseif ($uid) {
			$status = 0;
			$msg = "";
			require_once ROOT . './plugins/zw_client_api/BaiduUtil.php';
			$binded_baidu = true;
			try {
				$baiduUtil = new BaiduUtil(get_cookie($uid));
			} 
			catch(Exception $e) {
				if ($e -> getCode() == 10) $binded_baidu = false;
			} 
			switch ($_GET['a']) {
				case 'baidu_account_info':
					$msg = "百度账号信息";
					$baidu_account_info = $baiduUtil -> fetchClientUserInfo();
					$baidu_account_tieba_list = $baiduUtil -> fetchClientLikedForumList();
					$baidu_account_follow_list = $baiduUtil -> fetchFollowList(4);
					$baidu_account_fans_list = $baiduUtil -> fetchFansList(4);
					$data = array('username' => $baidu_account_info['data']['un'], 'avatar' => $baidu_account_info['data']['head_photo_h'], 'sex' => $baidu_account_info['data']['sex'], 'tb_age' => $baidu_account_info['data']['tb_age'], 'fans_num' => $baidu_account_info['data']['fans_num'], 'follow_num' => $baidu_account_info['data']['concern_num'], 'tb_num' => $baidu_account_info['data']['like_forum_num'], 'intro' => $baidu_account_info['data']['intro']?$baidu_account_info['data']['intro']:'这个家伙很懒，什么也没有留下', 'tiebas' => $baidu_account_tieba_list['data'], 'follow' => $baidu_account_follow_list['data'], 'fans' => $baidu_account_fans_list['data'],);
					break;
				case 'sign_log':
					$msg = "获取成功";
					$date = intval($_GET['date']);
					$data['date'] = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
					$data['log'] = array();
					$query = DB :: query("SELECT * FROM sign_log l LEFT JOIN my_tieba t ON t.tid=l.tid WHERE l.uid='{$uid}' AND l.date='{$date}'");
					while ($result = DB :: fetch($query)) {
						$data['log'][] = $result;
					} 
					$data['count'] = count($data['log']);
					$data['before_date'] = DB :: result_first("SELECT date FROM sign_log WHERE uid='{$uid}' AND date<'{$date}' ORDER BY date DESC LIMIT 0,1");
					$data['after_date'] = DB :: result_first("SELECT date FROM sign_log WHERE uid='{$uid}' AND date>'{$date}' ORDER BY date ASC LIMIT 0,1");
					break; 
				// NOTICE : Just For Test;
				case 'test': 
					// $data = $baidu -> get_user_info();
					// echo var_dump($baidu);
					$data = $baiduUtil -> fetchClientUserInfo();
					break;
			} 
		} 
		echo json_encode(array('status' => $status, 'msg' => $msg, 'data' => $data));
	} 
} 
