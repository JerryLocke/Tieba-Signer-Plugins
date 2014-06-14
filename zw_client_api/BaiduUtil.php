<?php
/*
 * baidu class | Version 1.0.1 | Copyright 2014, Cai Cai | Released under the MIT license
 * login、sign、post、zan、meizhi、tdou
 */
class BaiduUtil{

	public $useZlib       = FALSE;
	public $returnThis    = FALSE;
	public $lastFetch     = array();
	public $lastReturn    = array();
	public $lastformData  = array();
	
	protected $un         = '';
	protected $uid        = '';
	protected $tbs        = '';
	protected $bduss      = '';
	protected $cookie     = '';
	protected $client     = array();
	protected $formData   = array();
	protected $forumPages = array();

	public function __construct($cookie = NULL, $userinfo = array(), $client = NULL){
		if(!is_null($cookie)){
			$cookie = trim($cookie);
			$temCookieHasBduss = stripos($cookie,'bduss=');
			$temCookieHasSemicolon = stripos($cookie,';');
			if($temCookieHasBduss === FALSE &&  $temCookieHasSemicolon === FALSE){
				$this->bduss = $cookie;
			}elseif($temCookieHasBduss !== FALSE && $temCookieHasSemicolon === FALSE){
				$this->bduss = substr($cookie,6);
			}elseif(preg_match('/bduss\s?=\s?([^ ;]*)/i', $cookie, $matches)){
				$this->bduss = $matches[1];
			}else{
				throw new Exception('请输入合法的cookie',-99);
			}
			$this->cookie = $this->buildFullCookie();
		}
		if(is_null($client)){
			$this->client = self::getClient();
		}else{
			$this->client = $client;
		}
		if(isset($userinfo['un'])) $this->un = $userinfo['un'];
		if(isset($userinfo['uid'])) $this->uid = $userinfo['uid'];
	}

	protected function fetch($url,$mobile = TRUE,$usecookie = TRUE){
		$ch = curl_init($url);
		if($mobile === TRUE){
			$common_data = array(
					'from'        => 'baidu_appstore',
					'stErrorNums' => '0',
					'stMethod'    => '1',
					'stMode'      => '1',
					'stSize'      => rand(50,2000),
					'stTime'      => rand(50,500),
					'stTimesNum'  => '0',
					'timestamp'   => time() . self::random(3,TRUE)
			);
			$predata = $this->client + $this->formData + $common_data;
			ksort($predata);
			$this->formData = array();
			if($usecookie === TRUE){
				$this->formData['BDUSS'] = $this->bduss;
			}
			$this->formData += $predata;
			$sign_str = '';
			foreach($this->formData as $key=>$value)
				$sign_str .= $key . '=' . $value;
			$sign = strtoupper(md5($sign_str . 'tiebaclient!!!'));
			$this->formData['sign'] = $sign;
			$http_header = array(
					'User-Agent: BaiduTieba for Android 6.0.1',
					'Content-Type: application/x-www-form-urlencoded',
					'Host: c.tieba.baidu.com',
					'Connection: Keep-Alive'
			);
			if($this->useZlib === TRUE) $http_header[] = 'Accept-Encoding: gzip';
		}else{
			$http_header = array(
					'User-Agent: Mozilla/5.0 (Windows NT 6.3; rv:29.0) Gecko/20100101 Firefox/29.0',
					'Connection: Keep-Alive'
			);
			curl_setopt($ch,CURLOPT_COOKIE,$this->cookie);
		}
		curl_setopt($ch,CURLOPT_HTTPHEADER,$http_header);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
		curl_setopt($ch,CURLOPT_POST,TRUE);
		curl_setopt($ch,CURLOPT_TIMEOUT,10);
		curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($this->formData));
		$res_json = curl_exec($ch);
		curl_close($ch);
		if(empty($res_json)) throw new Exception('网络连接失败',-10);
		if($this->useZlib === TRUE) $res_json = gzdecode($res_json);
		$result = @json_decode($res_json,TRUE);
		if($mobile === TRUE){
			if(!array_key_exists('error_code',$result)) throw new Exception('未收到正确数据',-11);
			if(!empty($result['anti']['tbs'])) $this->tbs = $result['anti']['tbs'];
			if(!empty($result['user']['id']))  $this->uid = $result['user']['id'];
			if(!empty($result['user']['name'])) $this->un = $result['user']['name'];
		}
		$this->last_formData = $this->formData;
		$this->formData      = array();
		$this->lastFetch      = $result;
		return $result;
	}

	public function returnThis(){
		$this->returnThis = TRUE;
		return $this;
	}

	public static function simpleFetch($url){
		$ch = curl_init($url);
		curl_setopt($ch,CURLOPT_HTTPHEADER,array(
			'User-Agent: Mozilla/5.0 (Windows NT 6.3; rv:29.0) Gecko/20100101 Firefox/29.0',
			'Connection: Keep-Alive'
		));
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		$content = curl_exec($ch);
		curl_close($ch);
		$content = json_decode($content,true);
		return $content;
	}

	protected function commonReturn($data){
		$result = array();
		if(isset($data['no']) && !isset($data['errorcode'])) $data['error_code'] = $data['no'];
		if(isset($data['error']) && !isset($data['error_msg'])) $data['error_msg'] = $data['error'];
		if($data['error_code'] == 0 && !is_null($data['error_code'])){
			$data['error_msg'] = "执行成功";
		}elseif(!isset($data['error_msg'])){
			$data['error_msg'] = "未知错误,错误代码" . $data['error_code'];
		}else{
			$data['error_msg'] .= " return=" . $data['error_code'];
		}
		$result['status'] = $data['error_code'];
		$result['msg'] = $data['error_msg'];
		if(isset($data['i']) && is_array($data['i'])){
			foreach ($data['i'] as $key => $value) {
				$result['data'][$key] = $value;
			}
		}
		$this->lastReturn = $result;
		return $result;
	}

	public static function random($length,$numeric = FALSE){
		$seed = base_convert(md5(microtime() . $_SERVER['DOCUMENT_ROOT']),16,$numeric?10:35);
		$seed = $numeric?(str_replace('0','',$seed) . '012340567890'):($seed . 'zZ' . strtoupper($seed));
		$hash = '';
		$max = strlen($seed) - 1;
		for($i = 0 ; $i < $length ; $i++){
			$hash .= $seed{mt_rand(0,$max)};
		}
		return $hash;
	}

	protected function clientRelogin(){
		$this->formData = array(
				'bdusstoken' => $this->bduss
		);
		$result = $this->fetch('http://c.tieba.baidu.com/c/s/login');
		if($result['error_code'] != 0){
			switch ($result['error_code']) {
				case 1:
				case 1990006:
					throw new Exception('用户未登录或登录失败，请更换账号或重试',-19);
					break;
				default:
					throw new Exception('Relogin失败,status:'.$result['error_code'].',msg'.$result['error_msg'],-15);
					break;
			}
		}
	}

	public function un(){
		if(empty($this->un)) $this->clientRelogin();
		if(empty($this->un)){
			$result = $this->fetchWebUserPrivateInfo();
			$this->un = $result['data']['un'];
		}
		return $this->un;
	}

	public function uid(){
		if(empty($this->uid)) $this->clientRelogin();
		return $this->uid;
	}

	public function tbs(){
		if(empty($this->tbs)) $this->clientRelogin();
		return $this->tbs;
	}

	public function fetchWebTbs(){
		if(!empty($this->tbs)) return $this->tbs;
		$result = $this->fetch('http://tieba.baidu.com/dc/common/tbs',FALSE);
		if(array_key_exists('is_login',$result) === TRUE && $result['is_login'] === 0) throw new Exception('获取webtbs失败',-14);
		return $result['tbs'];
	}

	public static function fetchUid($un){
		$result = self::fetchWebUserInfo($un);
		return $result['data']['uid'];
	}

	public static function fetchHeadPhoto($un){
		$result = self::fetchWebUserInfo($un);
		return $result['data']['head_photo'];
	}

	public function fetchClientUserInfo($uid = NULL){
		if(is_null($uid)){
			$temIsOwner = '1';
			$temUid     = $this->uid();
		}else{
			$temIsOwner = '0';
			$temUid     = $uid;
		}
		$this->formData=array(
			'has_plist'       =>'1',
			'is_owner'        =>$temIsOwner,
			'need_post_count' =>'1',
			'pn'              =>'1',
			'rn'              =>'20',
			'uid'             =>$temUid,
		);
		$result=$this->fetch("http://c.tieba.baidu.com/c/u/user/profile");
		$result['i']=array(
			'id'			 =>$result['user']['id'],
			'un'             =>$result['user']['name'],
			'sex'            =>$result['user']['sex'],
			'tb_age'         =>$result['user']['tb_age'],
			'fans_num'       =>$result['user']['fans_num'],
			'concern_num'    =>$result['user']['concern_num'], /*关注数*/
			'like_forum_num' =>$result['user']['like_forum_num'],/*关注贴吧数*/
			'post_num'       =>$result['user']['post_num'],/*总发帖数*/
			'repost_num'     =>$result['user']['repost_num'],/*回复数*/
			'thread_num'     =>$result['user']['thread_num'],/*主题数*/
			'intro'          =>$result['user']['intro'],
			'head_photo'     =>'http://tb.himg.baidu.com/sys/portrait/item/'.$result['user']['portrait'],
			'head_photo_h'   =>'http://tb.himg.baidu.com/sys/portrait/item/'.$result['user']['portraith']
		);
		return $this->commonReturn($result);
	}

	public function fetchFansList($num = NULL){
		$result = $this->fetchFollowAndFansList('fans',$num);
		return $result;
	}

	public function fetchFollowList($num = NULL){
		$result = $this->fetchFollowAndFansList('follow',$num);
		return $result;
	}

	protected function fetchFollowAndFansList($type, $num){
		if($type == 'fans'){
			$result = $this->fetch('http://c.tieba.baidu.com/c/u/fans/page');
		}else{
			$result = $this->fetch('http://c.tieba.baidu.com/c/u/follow/page');
		}
		$temHeadPhoto = array ();
		foreach ($result['user_list'] as &$temFans) {
			$temFans['head_photo'] = 'http://tb.himg.baidu.com/sys/portrait/item/'.$temFans['portrait'];
			$temHeadPhoto[] = $temFans['head_photo'];
		}
		unset($temFans);
		$result['i']['user_list'] = $result['user_list'];//id intro is_followed name portrait
		$result['i']['head_photo_list'] = $temHeadPhoto;
		if((!is_null($num)) && ($num < count($temHeadPhoto))){
			$result['i']['user_list'] = array_slice($result['user_list'], 0, $num);
			$result['i']['head_photo_list'] = array_slice($temHeadPhoto, 0, $num);
		}
		return $this->commonReturn($result);
	}

	public function fetchClientLikedForumList(){
		$this->formData = array(
				'like_forum' => '1',
				'recommend' => '0',
				'topic' => '0'
		);
		$result = $this->fetch('http://c.tieba.baidu.com/c/f/forum/forumrecommend');
		$result['i'] = $result['like_forum'];//avatar贴吧头像 forum_id forum_name is_sign level_id
		return $this->commonReturn($result);
	}

	public function fetchClientMultisignForumList(){
		$this->formData = array(
				'user_id' => $this->uid()
		);
		$result = $this->fetch('http://c.tieba.baidu.com/c/f/forum/getforumlist');
		$result['i'] = $result['forum_info'];
		return $this->commonReturn($result);
	}

	public static function getClient($type = NULL,$model = NULL,$version = NULL){
		$client = array(
			'_client_id'      => 'wappc_138' . self::random(10,TRUE) . '_' . self::random(3,TRUE),
			'_client_type'    => is_null($type)?rand(1,4):$type,
			'_client_version' => is_null($version)?'6.0.1':$version,
			'_phone_imei'     => md5(self::random(16,TRUE)),
			'cuid'            => strtoupper(md5(self::random(16))) . '|' . self::random(15,TRUE),
			'model'           => is_null($model)?'M1':$model,
		);
		return $client;
	}

	public function getForumInfo($kw,$type = 'forum'){
		if(!array_key_exists($kw,$this->forumPages)) $this->fetchForumPage($kw);
		$forum = &$this->forumPages[$kw];
		switch($type){
			case 'post':
				$post_threads = array();
				foreach($forum['tlist'] as $thread){
					if($thread['is_top'] == 0 && $thread['is_posted'] == 0) $post_threads[] = $thread;
				}
				$post_thread = $post_threads[array_rand($post_threads)];
				$info = $post_thread['tid'];
				break;
			case 'zan':
				$zan_threads = array();
				foreach($forum['tlist'] as $thread){
					if(!isset($thread['is_zaned'])) throw new Exception("没有点赞信息", -18);
					if($thread['is_top'] == 0 && $thread['is_zaned'] == 0) $zan_threads[] = $thread;
				}
				if(!count($zan_threads)) throw new Exception('无可赞的帖子',-12);
				$zan_thread  = $zan_threads[array_rand($zan_threads)];
				$info['tid'] = $zan_thread['tid'];
				$info['pid'] = $zan_thread['pid'];
				break;
			case 'forum':
				$info['fid']        = $forum['fid'];
				$info['name']       = $forum['name'];
				$info['user_level'] = $forum['user_level'];
				break;
			case 'fid':
				$info = $forum['fid'];
		}
		return $info;
	}

	public function buildFullCookie(){
		return 'BAIDUID=' . strtoupper(self::random(32)) . ':FG=1;BDUSS=' . $this->bduss . ';';
	}

	public function login($un,$passwd,$vcode = NULL,$vcode_md5 = NULL){
		try{
			$this->formData = array (
					'isphone' => '0',
					'passwd'  => base64_encode($passwd),
					'un'      => $un
			);
			if(!is_null($vcode) && !is_null($vcode_md5)){
				$vcode_data = array(
						'vcode' => $vcode,
						'vcode_md5' => $vcode_md5
				);
				$this->formData += $vcode_data;
			}
			$result = $this->fetch('http://c.tieba.baidu.com/c/s/login',TRUE,FALSE);
			if($result['error_code'] == 0){
				$temRawBduss = $result['user']['BDUSS'];
				preg_match('/(.*)\|/', $temRawBduss, $matches);
				$this->bduss = $matches[1];
				$this->cookie = $this->buildFullCookie();
				$result['i'] = array(
						"uid"    => $result['user']['id'],
						"un"  => $result['user']['name'],
						"bduss" => $this->bduss,
						"cookie"=>$this->cookie,
				);
			}elseif($result['error_code'] == 5){
				$result['i'] = array(
					'un'            => $un,
					'passwd'        => base64_encode($passwd),
					"need_vcode"    => $result['anti']['need_vcode'],
					"vcode_md5"     => $result['anti']['vcode_md5'],
					"vcode_pic_url" => $result['anti']['vcode_pic_url'],
				);
			}
		}catch(Exception $e){
			$result['error_code'] = $e->getCode();
			$result['error_msg']  = $e->getMessage();
		}
		return $this->commonReturn($result);
	}

	public function sign($kw,$fid = NULL){
		try{
			if(is_null($fid)) $fid = $this->getForumInfo($kw,'fid');
			$this->formData = array(
				'fid' => $fid,
				'kw'  => $kw,
				'tbs' => $this->tbs()
			);
			$result = $this->fetch('http://c.tieba.baidu.com/c/c/forum/sign');
			$result['i'] = array(
				'fid' =>$fid,
				'kw'  =>$kw,
			);
		}catch(Exception $e){
			$result['error_code'] = $e->getCode();
			$result['error_msg']  = $e->getMessage();
		}
		return $this->commonReturn($result);
	}

	public function multiSign(){
		try{
			$forums = $this->fetchClientMultisignForumList();
			$forum_ids = '';
			if(!@count($forums)) throw new Exception("没有可以一键签到的贴吧", -17);
			foreach($forums['data'] as $forum){
				$forum_ids .= $forum['forum_id'] . ',';
			}		
			$forum_ids = substr($forum_ids,0,-1);
			$this->formData = array(
				'forum_ids' => $forum_ids,
				'tbs' => $this->tbs(),
				'user_id' => $this->uid(),
			);
			$result = $this->fetch('http://c.tieba.baidu.com/c/c/forum/msign');
			$result['i'] = $result['info'];
		}catch(Exception $e){
			$result['error_code'] = $e->getCode();
			$result['error_msg']  = $e->getMessage();
		}
		return $this->commonReturn($result);
	}

}

