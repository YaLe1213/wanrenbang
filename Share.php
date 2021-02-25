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
class Share extends Controller
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
     * 检测活动开启状态
     * @return [type] [description]
     */
    public function checkactivity()
    {
        $id = Db::name('share_activity')->max('id');
        $info = Db::name('share_activity')->where('id',$id)->field('status,end_time')->find();
        if ($info['status'] == 'false') {
            return json_error(0, '分享活动已暂时关闭');
        } else {
            if (strtotime($info['end_time']) <= time()) {
                return json_error(0, '分享活动已结束');
            }
        }
    }



    /**
     * 分享活动信息
     * @return [type] [description]
     */
    public function shareactive()
    {
        $this->checkactivity();
    	$max = Db::name('share_activity')->max('id');
    	$data = Db::name('share_activity')->where('id', $max)->field('id,title,end_time,price,desc,num,share_price')->find();
    	if (!empty($data)) {
    		// 添加会员到活动
    		if (false == Db::name('share_users')->where(['users_id'=>$this->uid, 'activity_id'=>$max])->find()) {
    			Db::name('share_users')->insert(['users_id'=>$this->uid, 'create_time'=>time(), 'activity_id'=>$max]);
    		}
            $data['end_time'] = strtotime($data['end_time'])-time()<=0 ? 0 : strtotime($data['end_time'])-time() ;
    		return json_success(1, $data['title'], $data);
    	} else {
    		return json_error(0, '暂无活动');
    	}
    }


    /**
     * 分享    判断任务是否已取消
     * @return [type] [description]
     */
    public function share()
    {
        $this->checkactivity();
    	$id = $this->request->param('id'); // 任务id
    	$where = [
            'is_stop'=>'false',    // 没有暂停
            'status'=>1,     // 进行中
            'id'=>$id,
        ];
    	if (false == Db::name('project')->where($where)->find()) {
    		return json_error(0, '数据错误！');
    	} else {
    		$activity_id = Db::name('share_activity')->max('id');
            $info = Db::name('project')->where('id',$id)->value('share_num');   // 最高分享次数
            $count = Db::name('share')->where('users_id',$this->uid)->where('project_id',$id)->count();
            if ($count >= $info) {
                return json_error(0,'当前任务分享已达上限');
            }
    		$res = Db::name('share')->insertGetId(['activity_id'=>$activity_id,'users_id'=>$this->uid,'project_id'=>$id,'create_time'=>time(),'update_time'=>'','is_cancel'=>0]);
    		if ($res) {
    			return json_success(1, '分享成功', ['id'=>$res]);
    		} else {
    			return json_error(0, '分享失败');
    		}
    	}
    }

    /**
     * 点击分享的消息---助力
     * @param int $id 分享id
     * @return [type] [description]
     * @return  id 分享助力id
     */
   	public function onclickshare()
   	{
        $this->checkactivity();
   		$id = $this->request->param('id');
   		$data = Db::name('share')->where('id',$id)->find();
   		if ($data) {
   			$res = Db::name('share_help')->insertGetId(['share_id'=>$id,'users_id'=>$this->uid,'is_help'=>0,'create_time'=>time(),'price'=>'','update_time'=>'']);
   			if ($res) {
   				return json_success(1, '成功', ['id'=>$res]);   // 分享id
   			} else {
   				return json_error(0,'失败');
   			}
   		} else {
   			return json_error(0, '数据错误！');
   		}
   	}

   	/**
   	 * 分享助力排行
   	 * @return [type] [description]
   	 */
   	public function sharesort()
   	{
        // 获取页数
        $page = $this->request->param('page', 1);

   		$id = Db::name('share_activity')->max('id');
   		$data['list'] = Db::name('share_users')->alias('su')->join('users u', 'u.id=su.users_id')->field('su.users_id,u.nickName')->select();    // 活动会员
   		
   		if (!empty($data['list'])) {
   			foreach ($data['list'] as $key => $value) {
	   			$share = Db::name('share')->where('activity_id',$id)->where('users_id',$value['users_id'])->select();  // 分享
	   			$num = 0;
	   			$price = 0;
	   			foreach ($share as $k => $v) {
	   				$num += Db::name('share_help')->where('is_help',1)->where('share_id',$v['id'])->count();   // 助力
	   				$price += Db::name('share_help')->where('is_help',1)->where('share_id',$v['id'])->sum('price');   // 助力
	   			}
   				$data['list'][$key]['num'] = $num;
   				$data['list'][$key]['price'] = $price;
	   		}
	   		$data['list'] = arraySort($data['list'], 'num');
   		}

        // 分页处理
        $data['sumpage'] = ceil(count($data['list'])/10);
        $data['list'] = $this->page($data['list'], 10, $page);

   		return json_success(1, '排行', $data);
   	}

    /**
     * 排行榜分页
     * @param  [type]  $array [description]
     * @param  integer $num   [description]
     * @param  integer $page  [description]
     * @return [type]         [description]
     */
    public function page($array,$num = 10,$page = 1)
    {
        if (empty($page)) {
            $page = 1;
        }
        $start=($page-1)*$num;
        $data = array_slice($array,$start,$num);
        return $data;
    }

   	/**
   	 * 分享助力---个人信息
   	 * @return [type] [description]
   	 */
   	public function sharemymation()
   	{
   		// 活动
   		$max = Db::name('share_activity')->max('id');
   		$ac = Db::name('share_activity')->where('id',$max)->value('num');
   		$data = Db::name('users')->where('id',$this->uid)->field('id,nickName,avatarUrl')->find(); // 自己信息
      $data['avatarUrl'] = addavatarUrl($data['avatarUrl']);
   		$share = Db::name('share')->where('activity_id',$max)->where('users_id',$this->uid)->select();  // 分享
		$today = 0;
		$num = 0;
		foreach ($share as $k => $v) {
			$today += Db::name('share_help')->where('is_help',1)->where('share_id',$v['id'])->where('create_time','between',[strtotime(date("Y-m-d")), strtotime(date("Y-m-d").'23:59:59')])->count();   // 今天助力
			$num += Db::name('share_help')->where('is_help',1)->where('share_id',$v['id'])->count();   // 全部助力
		}
   		$data['today'] = $today;
   		$data['num'] = $num >=$ac ? 0 : $ac-$num ;
   		return json_success(1, '分享活动个人信息', $data);
   	}

    /**
     * 分享列表--分享时间
     * @return [type] [description]
     */
    public function selfsharetime()
    {
        $cate = $this->request->param('cate');
        $page = $this->request->param('page', 1);
        $max = Db::name('share_activity')->max('id');
        $data = Db::name('share')->alias('s')->join('project p','p.id=s.project_id')->join('users u', 'u.id=p.users_id')->join('share_activity sa', 's.activity_id=sa.id')->field('s.id,p.users_id,s.project_id,u.avatarUrl,p.title,sa.share_price,sa.users_num,s.is_cancel,s.create_time')->where('s.activity_id',$max)->where('s.users_id',$this->uid)->select();  // 分享
        foreach ($data as $k => $v) {
            $data[$k]['avatarUrl'] = addavatarUrl($v['avatarUrl']);
            $data[$k]['yenum'] = Db::name('share_help')->where('is_help',1)->where('share_id',$v['id'])->count();   // 已助力
            $data[$k]['nonum'] = Db::name('share_help')->where('is_help',0)->where('share_id',$v['id'])->count();   // 未助力
            $data[$k]['price'] = Db::name('share_help')->where('is_help',1)->where('share_id',$v['id'])->sum('price');   // 未助力
        }

        if ($cate == 1) {           // 分享时间排序
            $data = arraySort($data, 'create_time');
        } else if ($cate == 2) {    // 完成次数排序
            $data = arraySort($data, 'yenum');
        } else if ($cate == 3) {    // 收益金额 排序
            $data = arraySort($data, 'price');
        }
        $data = $this->page($data, 10, $page);
        return json_success(1, '分享时间', $data);
    }

   	/**
   	 * 已助力
   	 * @return [type] [description]
   	 */
   	public function shareyenum()
   	{
   		$id = $this->request->param('id');
        $page = $this->request->param('page', 1);
   		$info = Db::name('share')->where('id',$id)->find();
   		if ($info) {
   			$data = Db::name('share_help')->alias('sh')->join('users u','u.id=sh.users_id')->join('share s', 's.id=sh.share_id')->join('project p','p.id=s.project_id')->join('share_activity sa','sa.id=s.activity_id')->field('u.avatarUrl,p.title,p.id,u.UID,sh.users_id,sh.price,sh.create_time')->where('sh.is_help', 1)->where('s.id',$id)->order('sh.create_time desc')->page($page, 20)->select();
        foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
            $data[$key]['create_time'] = date("m-d H:i", $value['create_time']);
        }
   			return json_success(1, '已助力', $data);
   		} else {
   			return json_error(0, '数据错误！');
   		}
   	}

   	/**
   	 * 未助力
   	 * @return [type] [description]
   	 */
   	public function sharenonum()
   	{
   		$id = $this->request->param('id');
        $page = $this->request->param('page', 1);
   		$info = Db::name('share')->where('id',$id)->find();
   		if ($info) {
   			$data = Db::name('share_help')->alias('sh')->join('users u','u.id=sh.users_id')->join('share s', 's.id=sh.share_id')->join('project p','p.id=s.project_id')->join('share_activity sa','sa.id=s.activity_id')->field('u.avatarUrl,p.title,p.id,u.UID,sh.users_id,sh.price,sh.create_time')->where('sh.is_help', 0)->where('s.id',$id)->order('sh.create_time desc')->page($page, 20)->select();
        foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
            $data[$key]['create_time'] = date("m-d H:i", $value['create_time']);
        }
   			return json_success(1, '未助力', $data);
   		} else {
   			return json_error(0, '数据错误！');
   		}
   	}


    /**
     * 已赚
     * @return [type] [description]
     */
    public function sharend()
    {
        $id = $this->request->param('id');
        $page = $this->request->param('page', 1);
        $info = Db::name('share')->where('id',$id)->find();
        if ($info) {
            $data = Db::name('share_help')->alias('sh')->join('users u','u.id=sh.users_id')->join('share s', 's.id=sh.share_id')->join('project p','p.id=s.project_id')->join('share_activity sa','sa.id=s.activity_id')->field('u.avatarUrl,p.title,p.id,u.UID,sh.users_id,sh.price,sh.create_time')->where('sh.is_help', 1)->where('sh.price', 'not null')->where('s.id',$id)->order('sh.create_time desc')->page($page, 20)->select();
        foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
            $data[$key]['create_time'] = date("Y-m-d H:i", $value['create_time']);
        }
            return json_success(1, '已赚', $data);
        } else {
            return json_error(0, '数据错误！');
        }
    }

   	/**
   	 * 取消分享
   	 * @return [type] [description]
   	 */
   	public function sharecancel()
   	{
   		$id = $this->request->param('id');
   		$info = Db::name('share')->where('id',$id)->find();
   		if ($info) {
   			if ($info['is_cancel'] == 1) {
   				return json_error(0, '已取消分享');
   			}
   			if (false == Db::name('share')->where('id',$id)->update(['is_cancel'=>1])) {
   				return json_error(0, '取消失败');
   			} else {
   				return json_success(1, '取消成功');
   			}
   		} else {
   			return json_error(0, '数据错误！');
   		}
   	}

}