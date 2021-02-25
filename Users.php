<?php

namespace app\api\controller;

use think\Session;
use think\Db;
use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Request; 

/**
 * 用户信息
 * @package app\api\controller
 */
class Users extends Controller
{
    public $uid='';
    protected function _initialize()
    {
        // 解密token
   //     $arr=Request::instance()->header('token');

			// if($arr==null){
			// 	http_response_code(401);
			// 	exit(json_error(2,'登录信息过期',[]));

			// }

			// $token = json_decode(checkToken($arr),true);
			// if($token['code']!=='200'){
			// 	http_response_code(401);
			// 	exit(json_error(2,'登录信息过期',$token));
			// }
   //        //解密token
   //      $tokens=check($arr);
        // $this->uid=json_decode(json_encode($tokens),true)['uid'];
        $this->uid=1;
    }
	public function getali()
	{
		$num= Db::name('users_withdrawal_ali')->where('users_id', $this->uid)->value('number');
		$name = Db::name('users_withdrawal_wx')->where('users_id', $this->uid)->value('name');

		$num = hide_phone($num);
		$name = substr_replace($name,'**',1,2);  

		return json_success(0, 'success', ['number'=>$num, 'name'=>$name]);
	}

	public function getwx()
	{
		$data = Db::name('users_withdrawal_wx')->where('users_id', $this->uid)->field('name,number')->find();

		$data['number'] = hide_phone($data['number']);
		$data['name'] = substr_replace($data['name'],'**',1,2);  

		return json_success(0, 'success',$data);
	}

/*******************************************个人信息******************************************/

    /**f
     * 个人中心--个人信息
     * @return [type] [description]
     */
	public function information()
	{
		$data = Db::name('users')->where('id',$this->uid)->find();
		if (!empty($data['phone'])) {
			$data['phone'] = hide_phone($data['phone']);
		}
		$data['avatarUrl'] = addavatarUrl($data['avatarUrl']);
		$data['getbalance'] = Db::name('project_order')->alias('po')->join('project p', 'p.id = po.project_id')->where(['po.users_id'=>$this->uid, 'po.status'=>['in','1,2']])->sum('p.price');

		return json_success(1,'我的资料',$data);
	}

	/**
	 * 我的账户流水
	 * @return [type] [description]
	 */
	public function usersflow()
	{
		$page = $this->request->param('page',1);
		$data = Db::name('users_trend')->where('users_id',$this->uid)->field('id,price,cate,status,create_time,cate2')->order('create_time desc')->page($page, 20)->select();
		return json_success(1, '我的账户流水', $data);
	}



	/**
	 * 流水报表
	 * @return [type] [description]
	 */
	public function usersflows()
	{
		$cate = $this->request->param('cate', 1);
		if ($cate == 1) {          //  今天
			$where = ['between', [strtotime(date("Y-m-d")), strtotime(date("Y-m-d").'23:59:59')]];
		} else if ($cate == 2) {       // 昨天
			$where = ['between', [strtotime(date("Y-m-d", strtotime('-1 day'))), strtotime(date("Y-m-d"))]];
		} else if ($cate == 3) {      // 本月
			$where = ['between', [strtotime(date("Y-m")), strtotime(date("Y-m-d").'23:59:59')]];
		} else if ($cate == 4) {      // 总收益
			$where = 'not null';
		}

		$data['profit'] = Db::name('users_trend')->where(['create_time'=>$where])->where(['cate'=>7, 'users_id'=>$this->uid])->sum('price');     // 盈利
		$data['consume'] = Db::name('users_trend')->where(['create_time'=>$where])->where(['cate'=>6, 'users_id'=>$this->uid])->sum('price');    // 消费
		$data['project'] = Db::name('project_order')->where(['create_time'=>$where])->where(['users_id'=>$this->uid])->count();    // 完成任务
		$data['projectprofit'] = Db::name('project_order')->alias('po')->join('project p', 'po.project_id=p.id')->where(['po.create_time'=>$where])->where('po.status', 3)->sum('p.price');    // 完成任务

		$nextlevel = Db::name('users')->where('pid',$this->uid)->column('id');    // 下级会员id
		$data['nextproject'] = Db::name('project_order')->where(['users_id'=>['in', $nextlevel], 'status'=>3])->count();

		$data['nextprojectprice'] = Db::name('project_order')->alias('po')->join('project p', 'po.project_id=p.id')->where(['po.users_id'=>['in', $nextlevel]])->where(['po.create_time'=>$where])->where('po.status', 3)->sum('p.price');
		$data['nextnum'] = count($nextlevel);
		$data['nextprice'] = Db::name('users_trend')->where(['users_id'=>['in',$nextlevel]])->sum('price');
		return json_success(1, '流水报表',$data);
	}

	/**
	 * 修改微信号 和 QQ 号
	 * @return [type] [description]
	 */
	public function editinformation()
	{
		if ($this->request->isPost()) {
			$post = $this->request->post();

	            $validate = new \think\Validate([
	                ['wx', 'require', '请输入微信号'],
	                ['qq', 'require|max:10', '请输入您的联系QQ号|QQ号最多10位！'],
	            ]);
	            if (!$validate->check($post)) {
	                return json_error(0,$validate->getError());
	            }
	            $post['update_time'] = time();
	            $res = Db::name('users')->where('id',$this->uid)->update($post);
	            if ($res) {
	            	return json_success(1,'修改成功');
	            } else {
	            	return json_error(0,'数据错误');
	            }
		} else {
			return json_error(0,'允许请求方式为POST');
		}
	}


	/**
	 * 修改会员信息
	 * @return [type] [description]
	 */
	public function editinformations()
	{
		if ($this->request->isPost()) {
			$post = $this->request->post();

            $validate = new \think\Validate([
                ['wx', 'require', '请输入真实微信号(非手机号)'],
                ['qq', 'require|max:10', '请输入联系QQ|联系QQ最多10位'],
                ['name', 'require', '请输入真实姓名'],
                ['idcard', 'require', '请输入真实身份证号'],
                ['phone', 'require|/^1[3456789]\d{9}$/', '请输入安全手机号|安全手机号格式错误'],
            ]);
            if (!$validate->check($post)) {
                return json_error(0,$validate->getError());
            }
            if (empty($post['code'])) {
            	return json_error(0, '请输入验证码');
            }
            $code = Db::name('smscode')->where('phone',$post['phone'])->value('code');
	        // return json(['code'=>$post['code'], 'service'=>$code]);
	        // 校验验证码
	        if ($post['code'] != $code) {
	            return json_error(0, '验证码错误，请重新输入');
	        }
            $post['update_time'] = time();
            $res = Db::name('users')->where('id',$this->uid)->update($post);
            if ($res) {
            	return json_success(1,'修改成功');
            } else {
            	return json_error(0,'数据错误');
            }
		} else {
			return json_error(0,'允许请求方式为POST');
		}
	}


	/**
	 * 获取我的id
	 * @return [type] [description]
	 */
	public function getmyid()
	{
		return json_success(1,'我的id',$this->uid);
	}


	/**
	 * 支付宝体现信息设置/修改
	 * @return [type] [description]
	 */
	public function myali()
	{
		$post = $this->request->param();
        $validate = new \think\Validate([
            ['number', 'require', '请输入真实账号'],
            ['phone', 'require|/^1[3456789]\d{9}$/', '请输入安全手机号|安全手机号格式错误'],
        ]);
        if (!$validate->check($post)) {
            return json_error(0,$validate->getError());
        }
        if (empty($post['code'])) {
        	return json_error(0, '请输入验证码');
        }
        $code = Db::name('smscode')->where('phone',$post['phone'])->value('code');
        // return json(['code'=>$post['code'], 'service'=>$code]);
        // 校验验证码
        if ($post['code'] != $code) {
            return json_error(0, '验证码错误，请重新输入');
        }
        $post['update_time'] = time();
        $post['users_id'] = $this->uid;
        
        if (false == Db::name('users_withdrawal_ali')->where('users_id',$this->uid)->find()) {
        	$post['create_time'] = time();
        	$res = Db::name('users_withdrawal_ali')->insertGetId(['number'=>$post['number'],'create_time'=>time(), 'update_time'=>time(),'users_id'=>$this->uid]);

        } else {
        	$res = Db::name('users_withdrawal_ali')->where('users_id',$this->uid)->update(['number'=>$post['number'], 'update_time'=>time()]);
        }
        // 第一次设置
        if (!empty($post['name']) && !empty($post['idcard'])) {
    		Db::name('users')->where('id',$this->uid)->update(['name'=>$post['name'], 'idcard'=>$post['idcard'], 'update_time'=>time()]);
    	}
        if ($res) {
        	return json_success(1, '保存成功');
        } else {
        	return json_error(0,'数据错误！');
        }
	}

	/**
	 * 微信体现信息设置/修改
	 * @return [type] [description]
	 */
	public function mywx()
	{
		$post = $this->request->param();
        $validate = new \think\Validate([
            ['name', 'require', '请输入真是姓名'],
            ['idcard', 'require', '请输入真实身份证号'],
            ['number', 'require', '请输入真实账号'],
            ['phone', 'require|/^1[3456789]\d{9}$/', '请输入安全手机号|安全手机号格式错误'],
        ]);
        if (!$validate->check($post)) {
            return json_error(0,$validate->getError());
        }
        if (empty($post['code'])) {
        	return json_error(0, '请输入验证码');
        }
        $code = Db::name('smscode')->where('phone',$post['phone'])->value('code');
        // return json(['code'=>$post['code'], 'service'=>$code]);
        // 校验验证码
        if ($post['code'] != $code) {
            return json_error(0, '验证码错误，请重新输入');
        }
        $post['update_time'] = time();
        $post['users_id'] = $this->uid;
        
        if (false == Db::name('users_withdrawal_wx')->where('users_id',$this->uid)->find()) {
        	$post['create_time'] = time();
        	$res = Db::name('users_withdrawal_wx')->insertGetId($post);
        } else {
        	$res = Db::name('users_withdrawal_wx')->where('users_id',$this->uid)->update($post);
        }
        if ($res) {
        	return json_success(1, '保存成功');
        } else {
        	return json_error(0,'数据错误！');
        }

	}



/*******************************************意见反馈******************************************/
	/**
	 * 意见反馈
	 * @return [type] [description]
	 */
	public function feedback()
	{
		if ($this->request->isPost()) {
			$post = $this->request->post();

            $validate = new \think\Validate([
                ['feedback_cate_id', 'require', '请选择反馈类型'],
                ['message', 'require', '请输入反馈内容'],
                ['wxnum', 'require', '请输入微信号'],
                ['qq', 'require|max:10', '请输入您的联系QQ号|联系QQ最多10位'],
            ]);
            if (!$validate->check($post)) {
                return json_error(0,$validate->getError());
            }

            if (!empty($post['image'])) {
            	$post['image'] = implode(',', $post['image']);
            }
            if (!empty($post['num'])) {
            	$post['num'] = $post['num'];
            }
            $post['users_id'] = $this->uid;
            $post['create_time'] = time();
            $post['update_time'] = time();
            $post['status'] = '1';   // 待处理
            $res = Db::name('feedback')->insert($post);
            if ($res) {
            	return json_success(1,'反馈成功');
            } else {
            	return json_error(0,'数据错误');
            }
		} else {
			return json_error(0,'允许请求方式为POST');
		}
	}


/*******************************************粉丝******************************************/

	public function myfans()
	{
		$data = Db::name('users_follow')->alias('uf')->join('users u','u.id=uf.users_id2')->field('uf.users_id2 as users_id,u.nickName,u.avatarUrl,u.UID,uf.create_time')->where('uf.users_id1',$this->uid)->select();
		foreach ($data as $key => $value) {
			$data[$key]['create_time'] = date('Y-m-d H:i', $value['create_time']);
			$data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
		}
		return json_success(0,'我的粉丝',$data);
	}



/*******************************************会员通知******************************************/
	
	/**
	 * 会员通知
	 * @return [type] [description]
	 */
	public function notice()
	{
		$data = Db::name('users_notice')->where('users_id',$this->uid)->field('id,title,message,create_time,is_look,tag')->select();
		foreach ($data as $key => $value) {
			$data[$key]['create_time'] = date("Y-m-d H:i:s", $value['create_time']);
			if (!empty($value['tag'])) {
				$data[$key]['tag'] = explode(',', $value['tag']);
			}
		}
		return json_success(1, '通知消息', $data);
	}

	/**
	 * 通知详情
	 * @return [type] [description]
	 */
	public function noticedetails()
	{
		$id = $this->request->has('id') ? $this->request->param('id') : 0;
		if ($id > 0) {
			$data = Db::name('users_notice')->where('id',$id)->field('id,title,message,create_time,is_look,tag')->find();
			if ($data) {
				if ($data['is_look'] == 0) {
					Db::name('users_notice')->where('id',$id)->update(['is_look'=>1]);
				}
				$data['create_time'] = date("Y-m-d H:i:s", $data['create_time']);
				if (!empty($data['tag'])) {
					$data['tag'] = explode(',', $data['tag']);
				}
				return json_success(1, $data['title'], $data);
			} else {
				return json_error(0, '数据错误！');
			}
		} else {
			return json_error(0, '数据错误！');
		}
	}

	/**
	 * 删除通知消息
	 * @return [type] [description]
	 */
	public function noticedelete()
	{
		$id = $this->request->has('id') ? $this->request->param('id') : 0;
		if ($id > 0) {
			$data = Db::name('users_notice')->where('id',$id)->find();
			if ($data) {
				if (false == Db::name('users_notice')->where('id',$id)->delete()) {
					return json_error(0,'删除失败');
				} else {
					return json_success(1, '删除成功');
				}
			} else {
				return json_error(0, '数据错误！');
			}
		} else {
			return json_error(0, '数据错误！');
		}
	}



	/**
	 * 修改头像
	 * @return [type] [description]
	 */
	public  function editavatarUrl()
	{
		$data = $this->request->param('avatarUrl');
		if (!empty($data)) {
			if (Db::name('users')->where('id',$this->uid)->value('edit_avatarUrl') == 0) {
				$res = Db::name('users')->where('id',$this->uid)->update(['avatarUrl'=>$data, 'edit_avatarUrl'=>'1']);
			} else {
				return json_error(0, '您已修改了一次，不可再次修改');
			}
		} else {
			return json_error(0, '数据错误！');
		}
	}

	/**
	 * 微信充值   1.下单 状态 为1 进行中  2.支付成功 状态 为2 完成 3.支付失败 状态 为0 失败
	 * @return [type] [description]
	 */
	public function wxrecharge()
	{
		$price = $this->request->param('price');
		if (isset($price) && !empty($price)) {
			// $res = Db::name('users')->where('id',$id)->setInc('use_balance',$price);
			$res = Db::name('users_trend')->insertGetId(['users_id'=>$this->uid, 'price'=>$price, 'cate'=>'1', 'status'=>'1', 'code'=>time().mt_rand(1000,9999), 'cate2'=>1, 'create_time'=>time()]);
			if ($res) {
				return json_success(1, 'success', ['id'=>$id]);
			} else {
				return json_error(0, 'error');
			}
		} else {
			return json_error(0, '请输入充值金额');
		}
	}

	/**
	 * 支付宝充值   1.下单 状态 为1 进行中  2.支付成功 状态 为2 完成 3.支付失败 状态 为0 失败
	 * @return [type] [description]
	 */
	public function alirecharge()
	{
		$price = $this->request->param('price');
		if (isset($price) && !empty($price)) {
			// $res = Db::name('users')->where('id',$id)->setInc('use_balance',$price);
			$res = Db::name('users_trend')->insertGetId(['users_id'=>$this->uid, 'price'=>$price, 'cate'=>'2', 'status'=>'1', 'code'=>time().mt_rand(1000,9999), 'cate2'=>1, 'create_time'=>time()]);
			if ($res) {
				return json_success(1, 'success', ['id'=>$res]);
			} else {
				return json_error(0, 'error');
			}
		} else {
			return json_error(0, '请输入充值金额');
		}
	}

	/**
	 * 微信提现   1.下单 状态 为1 进行中  2.支付成功 状态 为2 完成 3.支付失败 状态 为0 失败
	 * @return [type] [description]
	 */
	public function wxdral()
	{
		$price = $this->request->param('price');
		if (isset($price) && !empty($price)) {
			$res = Db::name('users')->where('id',$id)->setDec('use_balance',$price);
			if ($res) {
				Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$price, 'cate'=>'2', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>0, 'create_time'=>time()]);
				return json_success(1, '提现成功');
			} else {
				return json_error(0, '提现成功');
			}
		} else {
			return json_error(0, '请输入提现金额');
		}
	}

	/**
	 * 支付宝提现   1.下单 状态 为1 进行中  2.支付成功 状态 为2 完成 3.支付失败 状态 为0 失败
	 * @return [type] [description]
	 */
	public function alidral()
	{
		$price = $this->request->param('price');
		if (isset($price) && !empty($price)) {
			$res = Db::name('users')->where('id',$id)->setDec('use_balance',$price);
			if ($res) {
				Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$price, 'cate'=>'2', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>0, 'create_time'=>time()]);
				return json_success(1, '提现成功');
			} else {
				return json_error(0, '提现成功');
			}
		} else {
			return json_error(0, '请输入提现金额');
		}
	}

	/*
	奖励列表
		cate  	1 新人奖励  
				2 雇主奖励		
				3 每日奖励		
	 */
	public function Reward(){
		$arr=[];
		$cate=$this->request->param('cate','1');
		//需要分页请在此处加  page
		$data=Db('reword')->order('sort desc')->where(['cate'=>$cate])->column('id');
		if($cate==3)$where['create_time']=['between',strtotime(date('Y-m-d 00:00:00')).','.strtotime(date('Y-m-d 23:59:59'))];
		foreach($data as $k=>$v){
			$where=['cate_id'=>$v,'users_id'=>$this->uid];
			$is=Db('users_reward')->where($where)->count();
			if($is==0){
				$arr[$k]=Db('reword')->field('id,name,is_random,price1,price2')->where('id',$v)->find();
			}else{
				$arr[$k]=Db('reword')->field('id,desc')->where('id',$v)->find();
			}
			$arr[$k]['is']=$is;
		}
		return json($arr);
	}
	
	public function reword(Request $req)
	{
		$today_order_count = Db::name('project_order')->where(['users_id'=>$this->uid, 'status'=>3, 'create_time'=>['between',strtotime(date('Y-m-d 00:00:00')).','.strtotime(date('Y-m-d 23:59:59'))]])->count();    // 获取今日做的任务数量

		$data=Db('reword')->order('sort desc')->where(['cate'=>3])->field('id,name,is_random,price1,price2')->select();
		foreach ($data as $key => $value) {
			
			$where = [
				'cate_id'=>$value['id'],
				'users_id'=>$this->uid,
				'create_time'=>['between',strtotime(date('Y-m-d 00:00:00')).','.strtotime(date('Y-m-d 23:59:59'))],
			];

			switch ($value['id']) {
				case '6':    // 完成 3 个任务    任务数量:3
					$data[$key]['start'] = $today_order_count;
					$data[$key]['end'] = 3;

					if ($today_order_count >= 3) {    // 可领取
						$is=Db('users_reward')->where($where)->count();
						if ($is) {    // 已领取
							$data[$key]['status'] = 2;
						} else {    // 未领取
							$data[$key]['status'] = 1;
						}
					} else {    // 不可领取
						$data[$key]['status'] = 0;
					}

					break;
				
				case '7':    // 在完成 3 个任务    任务数量:6
					$data[$key]['start'] = $today_order_count;
					$data[$key]['end'] = 6;

					if ($today_order_count >= 6) {    // 可领取
						$is=Db('users_reward')->where($where)->count();
						if ($is) {    // 已领取
							$data[$key]['status'] = 2;
						} else {    // 未领取
							$data[$key]['status'] = 1;
						}
					} else {    // 不可领取
						$data[$key]['status'] = 0;
					}

					break;
				
				case '8':    // 在完成 5 个任务    任务数量:11
					$data[$key]['start'] = $today_order_count;
					$data[$key]['end'] = 11;

					if ($today_order_count >= 11) {    // 可领取
						$is=Db('users_reward')->where($where)->count();
						if ($is) {    // 已领取
							$data[$key]['status'] = 2;
						} else {    // 未领取
							$data[$key]['status'] = 1;
						}
					} else {    // 不可领取
						$data[$key]['status'] = 0;
					}

					break;
				
				case '9':    // 在完成 8 个任务    任务数量:19
					$data[$key]['start'] = $today_order_count;
					$data[$key]['end'] = 19;

					if ($today_order_count >= 19) {    // 可领取
						$is=Db('users_reward')->where($where)->count();
						if ($is) {    // 已领取
							$data[$key]['status'] = 2;
						} else {    // 未领取
							$data[$key]['status'] = 1;
						}
					} else {    // 不可领取
						$data[$key]['status'] = 0;
					}

					break;
				
				case '10':    // 在完成 12 个任务    任务数量:31
					$data[$key]['start'] = $today_order_count;
					$data[$key]['end'] = 31;

					if ($today_order_count >= 31) {    // 可领取
						$is=Db('users_reward')->where($where)->count();
						if ($is) {    // 已领取
							$data[$key]['status'] = 2;
						} else {    // 未领取
							$data[$key]['status'] = 1;
						}
					} else {    // 不可领取
						$data[$key]['status'] = 0;
					}
					
					break;
				
				default:
					$data[$key]['status'] = 0;    // 未满足领取条件
					$data[$key]['start'] = 0;    // 任务进度 开始
					$data[$key]['end'] = 0;    // 任务进度 结束
					break;
			}

		}

		return json_success(1, 'success', $data);
	}
	/*
		用户领取奖励
	*/
	public function UsersReward($id){

		$today_order_count = Db::name('project_order')->where(['users_id'=>$this->uid, 'status'=>3, 'create_time'=>['between',strtotime(date('Y-m-d 00:00:00')).','.strtotime(date('Y-m-d 23:59:59'))]])->count();    // 获取今日做的任务数量
		// 判断是否满足领取条件
		switch ($id) {
			case '6':    // 完成 3 个任务    任务数量:3

				if ($today_order_count >= 3) {    // 可领取
					$is=Db('users_reward')->where($where)->count();
					if ($is) {    // 已领取
						return json_error(0, '已领取');
					}
				} else {    // 不可领取
					return json_error(0, '未满足领取条件');
				}

				break;
			
			case '7':    // 在完成 3 个任务    任务数量:6
				$data[$key]['start'] = $today_order_count;
				$data[$key]['end'] = 6;

				if ($today_order_count >= 6) {    // 可领取
					$is=Db('users_reward')->where($where)->count();
					if ($is) {    // 已领取
						return json_error(0, '已领取');
					}
				} else {    // 不可领取
					return json_error(0, '未满足领取条件');
				}

				break;
			
			case '8':    // 在完成 5 个任务    任务数量:11
				$data[$key]['start'] = $today_order_count;
				$data[$key]['end'] = 11;

				if ($today_order_count >= 11) {    // 可领取
					$is=Db('users_reward')->where($where)->count();
					if ($is) {    // 已领取
						return json_error(0, '已领取');
					}
				} else {    // 不可领取
					return json_error(0, '未满足领取条件');
				}

				break;
			
			case '9':    // 在完成 8 个任务    任务数量:19
				$data[$key]['start'] = $today_order_count;
				$data[$key]['end'] = 19;

				if ($today_order_count >= 19) {    // 可领取
					$is=Db('users_reward')->where($where)->count();
					if ($is) {    // 已领取
						return json_error(0, '已领取');
					}
				} else {    // 不可领取
					return json_error(0, '未满足领取条件');
				}

				break;
			
			case '10':    // 在完成 12 个任务    任务数量:31
				$data[$key]['start'] = $today_order_count;
				$data[$key]['end'] = 31;

				if ($today_order_count >= 31) {    // 可领取
					$is=Db('users_reward')->where($where)->count();
					if ($is) {    // 已领取
						return json_error(0, '已领取');
					}
				} else {    // 不可领取
					return json_error(0, '未满足领取条件');
				}
				
				break;
			
			default:
				return json_error(0, '数据错误！');
				break;
		}

		//检测用户是否登录
		//获取奖励数据
		$reword=Db('reword')->field('is_random,price1,price2,name')->find($id);
		$price=0;
		//得出奖励金额
		if($reword['is_random']){
			$price=mt_rand($reword['price1'],$reword['price2']);
		}else{
			$price=$reword['price2'];
		}

		$date=time();
		Db::startTrans();
	    try{
			$r=Db('reword')->where(['user_id'=>$this->uid,'cate_id'=>$id])->count();
			if($r===0){
	        	//用户的奖励
	        	Db('users_reward')->insert([
					'name'=>$reword['name'],
					'price'=>$price,
					'cate_id'=>$id,
					'create_time'=>$date,
					'users_id'=>$this->uid,
				]);
	        	Db('users_notice')->insert([
					'create_time'=>$date,
					'users_id'=>$this->uid,
					'title'=>'通知标题',
					'message'=>'通知内容',
					'is_look'=>0,
					'tag'=>'标签',
				]);
				Db('users_trend')->insert([
					'create_time'=>$date,
					'users_id'=>$this->uid,
					'price'=>$price,
					'cate'=>7,//消费类型   1 充值2 提现 3冻结 4 保证金 5 退款 6 消费 7 收入
					'status'=>2,
					'code'=>'订单编号',
					'cate2'=>'1',
				]);
	        	
	        	$pid=Db('users')->where('id',$this->uid)->value('pid');
	        	if($pid){
	        		//师傅的奖励
					Db('users_reward')->insert([
						'name'=>$reword['name'],
						'price'=>$price,
						'cate_id'=>$id,
						'date'=>$date,
						'user_id'=>$pid,
					]);
					$pid=Db('users')->where('id',$pid)->value('pid');
	        		if($pid){
	        			//师爷爷的奖励
						Db('users_reward')->insert([
							'name'=>$reword['name'],
							'price'=>$price,
							'cate_id'=>$id,
							'date'=>$date,
							'user_id'=>$pid,
						]);
	        		}
	        	}
	            $use_balance=bcadd(Db('users')->where('id',$this->uid)->value('use_balance'),$price,2);//计算余额
	            Db('users')->where('id',$this->uid)->update(['use_balance'=>$use_balance]);//修改余额		           
			}else{
				return json_error(0,'不可重复领取');
			}
			Db::commit();
	        return json_success(1,'领取成功');
	    } catch (\Exception $e) {
	        // 回滚事务
	        Db::rollback();
	        return json_error(0,'领取失败');
	    }			
	}



	/**
	 * 我的举报
	 * @return [type] [description]
	 */
	public function myreport()
	{
		$page = $this->request->param('page', 1);
		$data = Db::name('project_order_report')->alias('r')->join('users u', 'u.id=r.users_id')->field('r.users_id,u.nickName,u.avatarUrl,u.name,r.status,r.create_time')->where('r.users_id',$this->uid)->order('r.create_time desc')->page($page, 20)->select();
		foreach ($data as $key => $value) {
			$data[$key]['create_time'] = date("Y-m-d H:i",$value['create_time']);
			$data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
		}
		return json_success(1, '我的举报', $data);
	}

	/**
	 * 我被举报
	 * @return [type] [description]
	 */
	public function reported()
	{
		$page = $this->request->param('page', 1);
		$data = Db::name('project_order_report')->alias('r')->join('project_order o', 'o.id=r.project_order_id')->join('users u', 'u.id=r.users_id')->field('r.users_id,u.nickName,u.avatarUrl,u.name,r.status,r.create_time')->where('o.users_id',$this->uid)->order('r.create_time desc')->page($page, 20)->select();
		foreach ($data as $key => $value) {
			$data[$key]['create_time'] = date("Y-m-d H:i",$value['create_time']);
			$data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
		}
		return json_success(1, '我被举报', $data);
	}

 
	// /**
	//  * 会员充值提现页面数据
	//  * @return [type] [description]
	//  */
	// public function usersbalance()
	// {
	// 	$data = Db::name('users')->where('id',$this->uid)->where('use_balance,frozen_balance')->find();
	// 	$data['todaypofit'] = Db::name('users_trend')->where(['users_id'=>$this->uid, 'cate'=>7])->sum('price');
	// 	$data['invite'] = Db::name('users_trend')->where(['users_id'=>$this->uid, 'cate'=>9])->sum('price');
	// 	return json_success(1, 'success', $data);
	// }
	/**
	 * 会员充值提现页面数据
	 * @return [type] [description]
	 */
	public function usersbalance()
	{
		$data = Db::name('users')->where('id',$this->uid)->field('use_balance,frozen_balance')->find();
		$data['todaypofit'] = Db::name('users_trend')->where(['users_id'=>$this->uid, 'cate'=>7])->sum('price');
		$data['invite'] = Db::name('users_trend')->where(['users_id'=>$this->uid, 'cate'=>9])->sum('price');
		if (false == Db::name('users_withdrawal_wx')->where('users_id',$this->uid)->find()) {
			$data['wx'] = false;
		} else {
			$data['wx'] = true;
		}
		if (false == Db::name('users_withdrawal_ali')->where('users_id',$this->uid)->find()) {
			$data['ali'] = false;
		} else {
			$data['ali'] = true;
		}
		return json_success(1, 'success', $data);
	}
	/**
	 * 提现列表
	 * @return [type] [description]
	 */
	public function tixianlist()
	{
		$page = $this->request->param('page',1);
		$data = Db::name("users_trend")->where(['users_id'=>$this->uid, 'cate'=>2])->field('id,price,create_time,pay_type')->order('create_time desc')->page($page, 20)->select();
		foreach ($data as $key => $value) {
			$data[$key]['create_time'] = date("Y-m-d H:i", $value['create_time']);
		}
		return json_success(1, '提现记录',$data);
	}
}