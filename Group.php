<?php

namespace app\api\controller;

use think\Session;
use think\Db;
use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Request; 

/**
 * 群组信息
 * @package app\api\controller
 */
class Group extends Controller
{
    public $uid='';
    protected function _initialize()
    {
   //      // 解密token
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

   //      $tokens=check($arr);
        // $this->uid=json_decode(json_encode($tokens),true)['uid'];
        $this->uid=1;
    }


    /**
     * 进入群组
     * @return [type] [description]
     */
	public function joinGroup()
	{
		$id = $this->request->param('id');
		if (!empty($id)) {
			if (false == Db::name('users_group')->where('id',$id)->find()) {
				return json_error(0,'数据错误！');
			} else {
				if (false == Db::name('group_users')->where(['users_id'=>$this->uid, 'users_group_id'=>$id])->find()) {
					Db::name('group_users')->insert(['users_id'=>$this->uid, 'users_group_id'=>$id,'create_time'=>time()]);
				} else {
					Db::name('group_users')->where(['users_id'=>$this->uid, 'users_group_id'=>$id])->update(['create_time'=>time()]);
				}
				return json_success(1,'进入成功');
			}
		} else {
			return json_error(0,'数据错误！');
		}
	}

	/**
	 * 群组组成员
	 * @param int $group_id 群组id
	 * @return json 群组成员
	 */
	public function groupUsers()
	{
		$group_id = $this->request->has('group_id') ? $this->request->param('group_id') : 0	;
		if ($group_id) {
			$name = Db::name('users_group')->where('id',$group_id)->value('name');
			$data = Db::name('group_users')->alias('gu')->join('users u','gu.users_id=u.id')->field('gu.id,gu.users_id,u.avatarUrl,u.nickName,u.company')->where('gu.users_group_id',$group_id)->select();
			return json_success(1, $name, $data);
		} else {
			return json_error(0,'数据错误！');
		}
	}

	/**
	 * 获取指定群组的信息
	 * @return [type] [description]
	 */
	public function getprojectinformation()
	{
		$group_id = $this->request->has('group_id') ? $this->request->param('group_id') : 0	;
		if ($group_id) {
			$data = Db::name('users_group')->where('id',$group_id)->field('id,name,address')->find();
			if (!empty($data)) {
				return json_success(1, $data['name'], $data);
			} else {
				return json_error(0,'数据错误！');
			}
		} else {
			return json_error(0,'数据错误！');
		}
	}


	/**
	 * 获取群组消息
	 * @param int $group_id 群组id
	 * @param int $page 分页
	 */
	public function getGroupProject()
	{
		$post = $this->request->param();
		if (empty($post['group_id']) || empty($post['page']) || empty($post['cate'])) {
			return json_error(0, '数据错误！');
		} else {
			$page = $post['page'];
			$group_id = $post['group_id'];
			$cate = $post['cate'];

			$pagenum = 10;
			$start = ($page-1)*$pagenum;
			$end = $page*$pagenum;

			$data = Db::name('project')->alias('p')->join('users u','p.users_id=u.id')->field('p.id,p.users_id,p.create_time,p.message,p.image,u.avatarUrl,u.nickName,u.company')->where(['group_id'=>$group_id,'cate'=>$cate])->limit($start,$end)->order('create_time desc')->select();
			if (!empty($data)) {
                $data = arraySort($data, 'create_time');
            }
			foreach ($data as $key => $value) {
				$data[$key]['year'] = date('Y-m-d',$value['create_time']);
				$data[$key]['time'] = date('H:i',$value['create_time']);
				$data[$key]['create_time'] = date('Y-m-d H:i',$value['create_time']);
			}
			return json_success(1, '群组消息', $data);
		}
	}


	/**
	 * 发消息
	 */
	public function addProject()
	{
		$post = $this->request->post();
        // 判断是不是该组成员
        if (false == Db::name('group_users')->where(['users_group_id'=>$post['group_id'], 'users_id'=>$this->uid])->find()) {
        	return json_error(0,'数据错误！');
        }
        $validate = new \think\Validate([
            ['group_id', 'require', '数据错误！'],
            ['cate', 'require', '数据错误！'],
            ['message', 'require', '请输入消息内容'],
        ]);
        if (!$validate->check($post)) {
            return json_error(0,$validate->getError());
        }
        $post['users_id'] = $this->uid;
        $post['create_time'] = time();
        if (empty($post['image'])) {
        	$post['image'] = '';
        }
        $res = Db::name('project')->insert($post);
        if ($res) {
        	return json_success(1,'发送成功');
        } else {
        	return json_error(0,'数据错误！');
        }
	}


	/**
	 * 搜索消息
	 * @return [type] [description]
	 */
	public function searchproject()
	{
		$post = $this->request->post();
		if (empty($post['group_id']) || empty($post['key']) || empty($post['cate'])) {
			return json_error(0, '数据错误！');
		}
		if (false == Db::name('group_users')->where(['users_id'=>$this->uid, 'users_group_id'=>$post['group_id']])->find()) {
			return json_error(0, '数据错误！');
		}
		$data['list'] = Db::name('project')->alias('p')->join('users_group ug','ug.id=p.group_id')->join('users u', 'u.id=p.users_id')->field('p.message,p.users_id,p.create_time,p.image,u.nickName,u.avatarUrl,u.company')->where(['p.group_id'=>$post['group_id'], 'p.message'=>['like', '%'.$post['key'].'%']])->order('p.create_time desc')->select();
		$data['num'] = Db::name('project')->alias('p')->join('users_group ug','ug.id=p.group_id')->join('users u', 'u.id=p.users_id')->field('p.message,p.users_id,p.create_time,p.image,u.nickName,u.avatarUrl,u.company')->where(['p.group_id'=>$post['group_id'], 'p.message'=>['like', '%'.$post['key'].'%']])->order('p.create_time desc')->count();
		foreach ($data['list'] as $key => $value) {
			$data['list'][$key]['year'] = date('Y-m-d', $value['create_time']);
			$data['list'][$key]['time'] = date('H:i', $value['create_time']);
			$data['list'][$key]['create_time'] = date("Y-m-d H:i", $value['create_time']);
		}
		return json_success(1,'列表',$data);
	}

	/**
	 * 会员搜索
	 * @return [type] [description]
	 */
	public function searchusers()
	{
		$key = $this->request->param('key');
		if (empty($this->request->param('page'))) {
			$page = 1;			
		} else {
			$page = $this->request->param('page');
		}		
		$pagenum = 10;
		$start = (intval($page)-1)*$pagenum;
		$end = intval($page)*$pagenum;
		if (!empty($key)) {
			$data['list'] = Db::name('users')->whereor('nickName','like', '%'.$key.'%')->whereor('contact','like', '%'.$key.'%')->field('id,avatarUrl,nickName,company')->limit($start, $end)->select();
			$data['num'] = Db::name('users')->whereor('nickName','like', '%'.$key.'%')->whereor('contact','like', '%'.$key.'%')->field('id,avatarUrl,nickName,company')->count();
			return json_success(1, '会员搜索',$data);
		} else {
			$data['list'] = [];
			$data['num'] = 0;

			return json_success(1, '会员搜索',$data);
		}
	}

}