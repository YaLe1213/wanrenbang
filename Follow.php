<?php

namespace app\api\controller;

use think\Session;
use think\Db;
use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Request; 

/**
 * 关注信息
 * @package app\api\controller
 */
class Follow extends Controller
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

    /**
     * 获取我关注的会员id
     * @return [type] [description]
     */
    public function getfollowids()
    {
    	return Db::name('users_follow')->where('users_id1',$this->uid)->column('id');
    }

	/**
	 * 我的关注-----我的关注
	 * @return [type] [description]
	 */
	public function followlist()
	{
		$data = Db::name('users_follow')->alias('uf')->join('users u','u.id=uf.users_id2')->where('uf.users_id1',$this->uid)->field('uf.id,uf.users_id2,u.nickName,u.avatarUrl,u.UID,uf.create_time')->select();
		if (!empty($data)) {
			foreach ($data as $key => $value) {
				$data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
				$data[$key]['create_time'] = date("Y-m-d",$value['create_time']);
			}
		}
		return json_success(1,'我的关注',$data);
	}
	/**
	 * 我的关注----会员动态
	 * @return [type] [description]
	 */
	public function getprojects()
	{
		$ids = $this->getfollowids();
		$data = Db::name('project')->alias('p')->join('users u','u.id=p.users_id')->join('project_cate pc', 'p.project_cate_id=pc.id')->field('u.nickName,u.avatarUrl,p.users_id,p.id,p.title,p.price,p.num,p.tag,p.create_time,pc.name')->where('p.users_id','in',$ids)->select();
		if (!empty($data)) {
			foreach ($data as $key => $value) {
				$data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
				$usenum = Db::name('project_order')->where(['project_id'=>$value['id'],'status'=>['in','1,2,3,5']])->count();
				$surplus = bcsub($value['num'], $usenum) < 0 ? 0 : bcsub($value['num'], $usenum) ;
				$data[$key]['usenum'] = $usenum;
				$data[$key]['surplus'] = $surplus;
				$data[$key]['create_time'] = date("Y-m-d",$value['create_time']);
			}
		}
		return json_success(1,'',$data);
	}

	/**
	 * 会员关注和取消关注
	 */
	public function usersfollow()
	{
		$id = $this->request->param('id');
		$info = Db::name('users')->where('id',$id)->count();
		if ($info >=1) {
			if ($this->uid == $id) {
				return json_error(1,'自己不能关注自己');
			}
			if (false == Db::name('users_follow')->where(['users_id1'=>$this->uid, 'users_id2'=>$id])->find()) {
				$data = [
					'users_id1'=>$this->uid,
					'users_id2'=>$id,
					'create_time'=>time(),
				];
				$res = Db::name('users_follow')->insert($data);
				if ($res) {
					return json_success(1,'关注成功');
				} else {
					return json_error(0,'数据错误！');
				}
			} else {
				$res = Db::name('users_follow')->where(['users_id1'=>$this->uid, 'users_id2'=>$id])->delete();
				if ($res) {
					return json_success(1,'取消成功');
				} else {
					return json_error(0,'数据错误！');
				}
			}
		} else {
			return json_error(0,'数据错误！');
		}
	}

	/**
	 * 我的关注---HEADER
	 * @return [type] [description]
	 */
	public function getheader()
	{
		$data = Db::name('users')->where('id',$this->uid)->field('nickName,avatarUrl')->find();
		$data['avatarUrl'] = addavatarUrl($data['avatarUrl']);
		$data['myFollowNum'] = Db::name('users_follow')->where('users_id1',$this->uid)->count();
		$data['myFansNum'] = Db::name('users_follow')->where('users_id2',$this->uid)->count();
		return json_success(1, '我的关注---HEADER', $data);
	}

}