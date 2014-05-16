<?php
if (!defined('IN_KKFRAME')) exit('Access Denied!');
class plugin_zw_client_api extends Plugin {
	var $description = '安卓客户端 API，使用安卓客户端需要本插件的支持';
	var $version = '1.0.0';

	function handleAction() {
		global $uid;
		$status = -1;
		$msg = "未登录！";
		$data = array();
		if ($_SERVER['HTTP_USER_AGENT'] != 'Android Client For Tieba Signer') {
			exit(array('status' => -1, 'msg' => '非法操作', 'data' => ''));
		} elseif ($_GET['a'] == 'api_info') {
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
			switch ($_GET['a']) {
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
			} 
		} 
		echo json_encode(array('status' => $status, 'msg' => $msg, 'data' => $data));
	} 
} 
