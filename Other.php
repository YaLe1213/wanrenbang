<?php

namespace app\api\controller;

use think\Session;
use think\Db;
use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Request; 

/**
 * 他人信息
 * @package app\api\controller
 */
class Other extends Controller
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

// 曝光  

    /**
     * 其他人中心
     * @return [type] [description]
     */
    public function othercenter()
    {
    	$id = $this->request->has('id') ? $this->request->param('id') : 0 ;
    	$info = Db::name('users')->where('id',$id)->where('is_seal','true')->find();
    	if ($info) {
    		$data = [
    			'users_id'=>$info['id'],
    			'avatarUrl'=>$info['avatarUrl'],
    			'nickName'=>$info['nickName'],
    			'UID'=>$info['UID'],
    			'project_img'=> Db::name('project')->where(['users_id'=>$id, 'status'=>1])->count(),    // 上架中的个数
    			'project_sum'=> Db::name('project')->where(['users_id'=>$id])->count(),    // 总任务
    			'project_order'=> Db::name('project')->alias('p')->join('project_order po', 'po.project_id=p.id')->where(['po.status'=>'3'])->count(),    // 总完成订单
    			'report_sum'=> Db::name('project_order_report')->alias('r')->join('project_order po', 'r.project_order_id=po.id')->join('project p', 'po.project_id=p.id')->where(['p.users_id'=>$id])->count(),    // 总完成订单
    			'follow_me'=> Db::name('users_follow')->where('users_id2',$id)->count(),    // 总完成订单
                'light_today'=>Db::name('project_browse')->where('users_id',$id)->where('create_time', 'between', [strtotime(date("Y-m-d")), strtotime(date("Y-m-d").'23:59:59')])->count(),
                'light_sum'=>Db::name('project_browse')->where('users_id',$id)->count(),
                'bond'=>$info['bond_balance'],
    		];
            $data['avatarUrl'] = addavatarUrl($data['avatarUrl']);
    		if (false == Db::name('users_follow')->where(['users_id1'=>$this->uid, 'users_id2'=>$id])->find()) {
    			$data['follow'] = false;
    		} else {
    			$data['follow'] = true;
    		}
    		return json_success(1, $data['nickName'], $data);
    	} else {
    		return json_error(0, '数据错误！');
    	}
    }

    /**
     * 进行中的任务
     * @param int $id 会员的id
     * @param int $page 分页的页码
     * @return [type] [description]
     */
    public function projecting()
    {
    	$id = $this->request->has('id') ? $this->request->param('id') : 0 ;
    	$info = Db::name('users')->where('id',$id)->find();
    	if ($info) {
    		$page = $this->request->has('page') ? $this->request->param('page') : 1;
    		$num = 10;
    		$data = Db::name('project')->alias('p')->join('project_cate pc', 'pc.id=p.project_cate_id')->join('users u', 'u.id=p.users_id')->where(['p.users_id'=>$id, 'p.status'=>1])->field('u.avatarUrl,p.users_id,p.id,p.title,p.price,p.num,pc.name,p.tag,p.create_time')->page($page, $num)->order('create_time desc')->select();
    		foreach ($data as $key => $value) {
    			$data[$key]['create_time'] = date("Y-m-d",$value['create_time']);
    			$count = Db::name('project_order')->where('status', 'in', '1,2,3,5')->where('project_id',$value['id'])->count();
            	$data[$key]['usenum']= $count;   // 人已赚
            	$data[$key]['yunum'] =  $value['num'] - $count;       // 剩余数量
    			$data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
    			unset($data[$key]['num']);  // 删除任务总数
    		}
    		return json_success(1, '进行中的任务', $data);
    	} else {
    		return json_error(0, '数据错误！');
    	}
    }



    /**
     * 已完成
     * @param int $id 会员的id
     * @param int $page 分页的页码
     * @return [type] [description]
     */
    public function projectended()
    {
    	$id = $this->request->has('id') ? $this->request->param('id') : 0 ;
    	$info = Db::name('users')->where('id',$id)->find();
    	if ($info) {
    		$page = $this->request->has('page') ? $this->request->param('page') : 1;
    		$num = 10;
    		$data = Db::name('project')->alias('p')->join('project_cate pc', 'pc.id=p.project_cate_id')->join('users u', 'u.id=p.users_id')->where(['p.users_id'=>$id, 'p.status'=>3, 'end_time'=>['between',[time()-3600*48,time()]]])->field('u.avatarUrl,p.users_id,p.id,p.title,p.price,p.num,pc.name,p.tag,p.create_time')->page($page, $num)->order('create_time desc')->select();
    		foreach ($data as $key => $value) {
    			$data[$key]['create_time'] = date("Y-m-d",$value['create_time']);
    			$count = Db::name('project_order')->where('status', 'in', '1,2,3,5')->where('project_id',$value['id'])->count();
            	$data[$key]['usenum']= $count;   // 人已赚
            	$data[$key]['yunum'] =  $value['num'] - $count;       // 剩余数量
    			$data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
    			unset($data[$key]['num']);  // 删除任务总数
    		}
    		return json_success(1, '已完成', $data);
    	} else {
    		return json_error(0, '数据错误！');
    	}
    }


}