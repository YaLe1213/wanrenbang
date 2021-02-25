<?php

namespace app\api\controller;

use think\Session;
use think\Db;
use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Request; 

/**
 * 其他人信息
 * @package app\api\controller
 */
class Order extends Controller
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
   //      $tokens=check($arr);
        // $this->uid=json_decode(json_encode($tokens),true)['uid'];
        $this->uid=1;
                // 进行中 状态的订单   并且超时的任务
        $ordering_id = Db::name('project_order')->where(['status'=>1, 'end_time'=>['<=', time()]])->column('id');
        Db::name('project_order')->where('id', 'in', $ordering_id)->update(['status'=>4, 'ended_time'=>time()]);  // 更改订单状态

    }

    //////////////////////////////////////
    ///			赚客							 
    //////////////////////////////////////

    /**
     * 任务报名
     * @param int $id 任务id
     * @return [type] [description]
     */
    public function addorder()
    {
    	$id = $this->request->has('id') ? $this->request->param('id') : 0 ;
    	$res = Db::name('project')->where('id',$id)->find();

    	if ($res) {
        
            // 获取 订单数量
            $order = Db::name('project_order')->where('project_id',$id)->where('status', 'in', '1,2,3,5')->count();
            // 1.判断有没有暂停 
            if ($res['status'] == '2') { return json_error(0, '该任务已暂停'); }
            if ($res['is_stop'] == 'true') { return json_error(0, '该任务已暂停'); }
            // 2.判断有没有结束
            if ($res['status'] == '3') { return json_error(0, '该任务已结束'); }
            if (date("Y-m-d H:i:s") >= strtotime($res['end_time'])) { return json_error(0, '该任务已结束'); }
            // 3.判断还有没有次数
            if ($order >= $res['num']) { return json_error(0, '当前任务已没有'); }
            // 4.是否可以重复做任务
            $ordered = Db::name('project_order')->where(['users_id'=>$this->uid, 'project_id'=>$id, 'status'=>['neq', '4']])->count();
            if ($ordered >= $res['users_limit']) { return json_error(0, '当前任务已没有次数'); }

    		// $res['limit_end_time'] 限时审核
    		$data = [
    			'users_id'=>$this->uid,
    			'project_id'=>$id,
    			'code'=>time().mt_rand(100000,999999),
    			'status'=>1,
    			'out_desc'=>'',
    			'create_time'=>time(),
    			'end_time'=>time()+$res['limit_end_time']*3600,
                'share_id'=>$this->request->param('share_id'),
    		];
    		$ress = Db::name('project_order')->insert($data);
    		if ($ress) {
    			return json_success(1, '报名成功');
    		} else {
    			return json_error(0, '报名失败');
    		}
    	} else {
    		return json_error(0, '数据错误！');
    	}
    }

    // /**
    //  * 批量通过审核
    //  * @return [type] [description]
    //  */
    // public function examinepasses()
    // {
    //     $post = $this->request->post();
    //     if (isset($post['ids']) && !empty($post['ids'])) {
    //         $info = Db::name('project_order_examine')->where('id', 'in', $post['ids'])->field('id,project_order_id')->select();
    //         foreach ($info as $key => $value) {
    //             // 获取任务赏金
    //             $price = Db::name('project')->alias('p')->join('project_order po', 'po.project_id=p.id')->where('po.id',$value['project_order_id'])->field('p.users_id,p.price')->find();
    //     // return json($price);

    //             Db::startTrans();
    //             try {
    //                 // 更改 稿件 状态 为 2 审核通过
    //                 Db::name('project_order_examine')->where('id',$value['id'])->update(['status'=>2,'end_time'=>time()]);
    //                 // 更改 订单 状态 为 3 已采纳
    //                 Db::name('project_order')->where('id',$value['project_order_id'])->update(['status'=>3, 'ended_time'=>time()]);

    //                 // 减少雇主的冻结金额
    //                 Db::name('users')->where('id', $this->uid)->setDec('frozen_balance',$price['price']);
    //                 Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$price['price'], 'cate'=>'4', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>0, 'create_time'=>time()]);  // 会员金额减少记录
    //                 // 增加会员金额
    //                 Db::name('users')->where('id', $price['users_id'])->setInc('use_balance', $price['price']);

    //                 Db::name('users_trend')->insert(['users_id'=>$price['users_id'], 'price'=>$price['price'], 'cate'=>'7', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>1, 'create_time'=>time()]);  // 会员收入记录
    //                 // 提交事务
    //                 Db::commit();
    //             } catch (\Exception $e) {
    //                 // 回滚事务
    //                 Db::rollback();
    //                 return json_error(0,'审核中止');
    //             }
    //         }
    //         return json_success(1, '成功');
    //     } else {
    //         return json_error(0,'数据错误！');
    //     }    
    // }


    /**
     * 通过审核   检测是否从分享报名的  == 检测 订单share_id
     * @return [type] [description]
     */
    public function examinepass()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
        if ($id > 0) {

            $info = Db::name('project_order_examine')->where('id',$id)->find();   // 稿件内容
            // $order_id = Db::name('project_order_examine')->where('id',$id)->value('project_order_id');  //  订单id

            $order = Db::name('project_order')->where('id',$info['project_order_id'])->find();   // 订单信息
          
            $price = Db::name('project')->where('id', $order['project_id'])->find();    // 获取任务信息
            if (!empty($price['share_id'])) {

                $help = Db::name('share_help')->where('id',$order['share_id'])->find();    // 助力信息 

                $share = Db::name('share')->where('id',$help['share_id'])->find();   // 分享信息 

                $activity = Db::name('share_activity')->where('id',$share['activity_id'])->find();    // 获取活动信息

                // 检测两个任务id 是否相同
                if ($share['project_id'] == $price['project_id']) {
                    // 检测助力次数  奖励次数  是否已经奖励  是否足够奖励金额  增加分享人的金额 和 记录  
                    $helpcount = Db::name('share_help')->alias('sh')->join('share s', 's.id=sh.share_id')->where('s.activity_id',$activity['id'])->count();  // 当前活动助力次数
                    $helped = Db::name('share_help')->alias('sh')->join('share s', 's.id=sh.share_id')->where('is_help', 1)->where('sh.price', 'not null')->where('s.activity_id',$activity['id'])->count();    // 当前活动奖励 次数
                    // 计算应该奖励次数   总助力次数  除  次数   floor 舍去法取整
                    $num = floor($helpcount/$activity['users_num']);  // 应该奖励次数
                    // 判断奖励次数 小于 奖励次数
                    if ($helped < $num) {
                        // 奖励 当前助力
                        Db::name('share_help')->where('id',$order['share_id'])->update(['is_help'=>1,'price'=>$activity['price'], 'update_time'=>time()]);
                        // 增加会员金额
                        $users = Db::name('users')->where('id',$share['users_id'])->find();
                        Db::name('users')->where('id', $share['users_id'])->update(['use_balance'=>bcadd($users['use_balance'], $activity['price'])]);
                        // 添加金额记录
                        Db::name('users_trend')->insert(['users_id'=>$share['users_id'], 'price'=>$activity['price'], 'cate'=>7, 'status'=>2, 'code'=>time().mt_rand(100000,999999), 'create_time'=>time(), 'cate'=>1, 'message'=>'助力成功奖励', 'vip_id'=>'', 'pay_type'=>'']);
                    }
                }
            }

            Db::startTrans();
            try {
                // 更改 稿件 状态 为 2 审核通过
                Db::name('project_order_examine')->where('id',$id)->update(['status'=>2,'end_time'=>time()]);
                // 更改 订单 状态 为 3 已采纳
                Db::name('project_order')->where('id',$info['project_order_id'])->update(['status'=>3, 'ended_time'=>time()]);

                // 减少雇主的冻结金额
                Db::name('users')->where('id', $this->uid)->setDec('frozen_balance',$price['price']);
                Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$post['price'], 'cate'=>'4', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>0, 'create_time'=>time()]);  // 会员金额减少记录
                // 增加会员金额
                Db::name('users')->where('id', $price['users_id'])->setInc('use_balance', $price['price']);

                Db::name('users_trend')->insert(['users_id'=>$price['users_id'], 'price'=>$post['price'], 'cate'=>'7', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>1, 'create_time'=>time()]);  // 会员收入记录
                // 提交事务
                Db::commit();
                return json_success(1, '成功');
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return json_error(0, '失败');
            }
        } else {
            return json_error(0,'数据错误！');
        }
    }





    /**
     * 取消任务
     * @return [type] [description]
     */
    public function cancelproject()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0;
        $info = Db::name('project_order')->where(['id'=>$id, 'users_id'=>$this->uid])->find();
        if ($info) {
            $title = Db::name('project')->alias('p')->join('project_order po','po.project_id=p.id')->where('po.id',$id)->value('p.title');
            $res = Db::name('project_order')->where(['id'=>$id, 'users_id'=>$this->uid])->delete();
            if ($res) {
                Db::name('users_notice')->insert(['users_id'=>$this->uid, 'title'=>'您承接的悬赏任务已取消', 'message'=>'您承接的悬赏任务 '.$title.' ，由于您已取消任务，欢迎您下次回来']);
                return json_success(1, '取消成功');
            } else {
                return json_error(0, '取消失败');
            }
        } else {
            return json_error(0, '数据错误！');
        }
    }

// 1. 判断订单限时审核

// 2. 不采纳 系统通知消息

    /**
     * 任务进行中列表
     * @return json status 1 进行中 2 审核中 4 超时未提交
     * @return [type] [description]
     */
    public function ordering()
    {

    	$num = 10;
    	// 分页
    	$page = $this->request->param('page', 1);
    	$status = '1,2,4';
    	$data = Db::name('project_order')->alias('po')->join('project p','p.id=po.project_id')->join('users u','u.id=p.users_id')->field('po.id,po.project_id,u.avatarUrl,po.code,po.create_time,po.end_time,p.title,p.price,po.status')->where(['po.users_id'=>$this->uid,'po.status'=>['in',$status]])->page($page,$num)->order('po.create_time desc')->select();
    	foreach ($data as $key => $value) {

            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
    		$data[$key]['create_time'] = date("Y-m-d H:i",$value['create_time']);
    		$data[$key]['end_time'] = date("Y-m-d H:i",$value['end_time']);
    	}
    	return json_success(1, '进行中列表', $data);
    }

    /**
     * 已通过列表
     * @return [type] [description]
     */
    public function ordered()
    {
    	$num = 10;
    	// 分页
    	$page = $this->request->param('page', 1);
    	$status = '3';
    	$data = Db::name('project_order')->alias('po')->join('project p','p.id=po.project_id')->join('users u','u.id=p.users_id')->field('po.id,u.avatarUrl,po.code,po.create_time,po.ended_time,p.title,p.price,po.status')->where(['po.users_id'=>$this->uid,'po.status'=>['in',$status]])->page($page,$num)->order('po.create_time desc')->select();
    	foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
    		$data[$key]['create_time'] = date("Y-m-d H:i",$value['create_time']);
    		$data[$key]['ended_time'] = date("Y-m-d H:i",$value['ended_time']);
    	}
    	return json_success(1, '已通过列表', $data);
    }

    /**
     * 未通过列表
     * @return [type] [description]
     */
    public function orderno()
    {
    	$num = 10;
    	// 分页
    	$page = $this->request->param('page', 1);
    	$status = '5';
    	$data = Db::name('project_order')->alias('po')->join('project p','p.id=po.project_id')->join('users u','u.id=p.users_id')->field('po.id,u.avatarUrl,po.code,po.create_time,po.ended_time,p.title,p.price,po.status')->where(['po.users_id'=>$this->uid,'po.status'=>['in',$status]])->page($page,$num)->order('po.create_time desc')->select();
    	foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
    		$data[$key]['create_time'] = date("Y-m-d H:i",$value['create_time']);
    		$data[$key]['ended_time'] = date("Y-m-d H:i",$value['ended_time']);
    	}
    	return json_success(1, '未通过列表', $data);
    }

    /**
     * 查看不采纳原因
     * @return [type] [description]
     */
    public function vieworderno()
    {
    	$id = $this->request->has('id') ? $this->request->param('id') : 0 ;
    	$status = 5;
    	$res = Db::name('project_order_examine')->where('status',$status)->where('project_order_id',$id)->field('no_content,image')->find();
        if (!empty($res['image'])) {
            $res['image'] = explode(',', $res['image']);
        }
    	if ($res) {
    		return json_success(1, '成功', $res);
    	} else {
    		return json_error(0, '数据错误！');
    	}
    }

    /**
     * 修改状态
     * @return [type] [description]
     */
    public function editorderstatus()
    {
    	$post = $this->request->param();
    	if (!empty($post['status'])) {
    		$res = Db::name('project_order')->where('id',$post['id'])->update(['status'=>$post['status']]);
    		if ($res) {
    			return json_success(1, '修改成功');
    		} else {
    			return json_error(0,'修改失败');
    		}
    	} else {
    		return json_error(0, '数据错误！');
    	}
    }

    /**
     * 删除记录
     * @return [type] [description]
     */
    public function delprojectorder()
    {
    	$id = $this->request->has('id') ? $this->request->param('id') : 0 ;
    	$res = Db::name('project_order')->where('id',$id)->find();
    	if ($res) {
    		$ress = Db::name('project_order')->where('id',$id)->delete();
    		if ($ress) {
    			return json_success(1, '删除成功');
    		} else {
    			return json_error(0, '删除失败');
    		}
    	} else {
    		return json_error(0, '数据错误！');
    	}
    }

    /**
     * 订单举报
     * $param id 		订单id
     * @param text $message 举报内容
     * @param url $image1 图片
     * @param url $image2 视频
     * @return [type] [description]
     */
    public function report()
    {
    	if ($this->request->isPost()) {
			$id = $this->request->has('id') ? $this->request->param('id') : 0 ;
	    	$res = Db::name('project_order')->where('id',$id)->find();
	    	if ($res) {
	    		$post = $this->request->post();
	            $validate = new \think\Validate([
	                ['message', 'require', '请填写举报内容'],
	            ]);
	            if (!$validate->check($post)) {
	                return json_error(0,$validate->getError());
	            }
	    		$data = [
	    			'users_id'=>$this->uid,
	    			'project_order_id'=>$id,
	    			'create_time'=>time(),
	    			'status'=>1, // 待审核
	    			'message'=>$post['message'],
                    'iamge1'=>'',
                    'iamge2'=>'',
	    		];
                if (!empty($post['image'])) {
                    $data['image'] = implode(',', $psot['image']);
                }
	    		$ress = Db::name('project_order_report')->insert($data);
	    		if ($ress) {
	    			return json_success(1, '举报成功，请等待审核');
	    		} else {
	    			return json_error(0, '举报失败');
	    		}
	    	} else {
	    		return json_error(0, '数据错误！');
	    	}
    	} else {
    		return json_error(0, '请求方式不允许');
    	}
    }

    /**
     * 浏览记录
     * @param int $cate 1 今天 2 昨天 
     * @param int $page 分页
     * @return [type] [description]
     */
    public function projectbrowse()
    {
    	$num = 10;
    	// 分页
        $page = $this->request->param('page', 1);
    	
    	// 搜索
    	if (!empty($post['keywords'])) {
    		$where['p.title'] = ['like','%'.$post['keywords'].'%'];
    	}
    	// 日期
    	if (!empty($post['cate'])) {
    		if ($post['cate'] == 1) {   // 今天
    			$startime = strtotime(date("Y-m-d"));
    			$endtime = strtotime(date("Y-m-d").'23:59:59');
    		} else {
    			$startime = strtotime(date("Y-m-d", strtotime('-1 day')));
    			$endtime = strtotime(date("Y-m-d", strtotime('-1 day')).'23:59:59');
    		}
    	} else {
    		$startime = strtotime(date("Y-m-d"));
    		$endtime = strtotime(date("Y-m-d").'23:59:59');
    	}
    	$where['pb.create_time'] = ['in',[$startime, $endtime]];
    	$where['pb.users_id'] = $this->uid;
    	$data = Db::name('project_browse')->alias('pb')->join('project p','p.id=pb.project_id')->join('users u','u.id=p.users_id')->field('pb.project_id,u.avatarUrl,p.title,pb.create_time')->where($where)->page($page, $num)->order('pb.create_time desc')->select();
    	foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
    		$data[$key]['create_time'] = date('Y-m-d H:i',$value['create_time']);
    	}
    	return json_success(1, '浏览记录', $data);
    }



    //////////////////////////////////////
    /// 	店铺 === 雇主
    //////////////////////////////////////

    /**
     * 进行中
     * @return [type] [description]
     */
    public function myprojecting()
    {
        $num = 10;
        // 分页
        $page = $this->request->param('page', 1);
        
    	$data = Db::name('project')->alias('p')->join('users u','p.users_id=u.id')->field('p.id,p.title,p.price,p.num,p.status,p.create_time,p.end_time,p.is_top,u.avatarUrl,u.UID')->where('p.status',1)->where('p.users_id',$this->uid)->order('p.create_time desc')->page($page,$num)->select();
        foreach ($data as $key => $value) {
            if ($data[$key]['is_top'] == 'true') {
                $data[$key]['is_top'] = true;
            } else {
                $data[$key]['is_top'] = false;
            }
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
            $count = Db::name('project_order')->where('status', 'in', '1,2,3,5')->where('project_id',$value['id'])->count();
            $data[$key]['browse'] = Db::name('project_browse')->where('project_id',$value['id'])->count();
            $data[$key]['yunum'] =  $value['num'] - $count;       // 剩余数量
            $data[$key]['reserve'] = $count != 0 ? $count*$value['price']:0;
        }
        return json_success(0, '进行中', $data);
    }

    /**
     * 暂停列表
     * @return [type] [description]
     */
    public function myprojectstop()
    {
        $num = 10;
        // 分页
        $page = $this->request->param('page', 1);
        $data = Db::name('project')->alias('p')->join('users u','p.users_id=u.id')->field('p.id,p.title,p.price,p.num,p.status,p.create_time,p.end_time,u.avatarUrl,u.UID')->where('p.status',2)->where('p.users_id',$this->uid)->order('p.create_time desc')->page($page,$num)->select();

        foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
            $count = Db::name('project_order')->where('status', 'in', '1,2,3,5')->where('project_id',$value['id'])->count();
            $data[$key]['browse'] = Db::name('project_browse')->where('project_id',$value['id'])->count();
            $data[$key]['yunum'] =  $value['num'] - $count;       // 剩余数量
            $data[$key]['reserve'] = $count != 0 ? $count*$value['price']:0;
        }
        return json_success(0, '暂停列表', $data);
    }

    /**
     * 结束任务
     * @return [type] [description]
     */
    public function endproject()
    {
        $id = $this->request->param('id', 0);
        $info = Db::name('project')->where('id',$id)->count();
        if ($info) {
            $users = Db::name('users')->where('id',$this->uid)->field('use_balance,frozen_balance')->find();
            // 计算余额
            $info = Db::name('project_order')->where('project_id',$id)->where('status','3')->count();   // 已采纳数量
            
            $endproject = Db::name('project_parameter')->where('id',1)->value('endproject');// 获取手续费
           
            $param = Db::name('project')->where('id',$id)->field('price,num')->find(); // 获取单价
            $yuprice = bcmul(bcsub($param['num'], $info), $param['price'], 2);   // 剩余任务金额


            // 启动事务
            Db::startTrans();
            try{
                // 恢复金额
                Db::name('users')->where('id',$this->uid)->setDec('frozen_balance',$yuprice);
                Db::name('users')->where('id',$this->uid)->setDec('use_balance', bcsub($yuprice, $endproject));  // 减掉手续费

                Db::name('project')->where('id',$id)->update(['status'=>3]);  // 修改订单状态
                // 添加金额记录
                Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$endproject, 'cate'=>6, 'status'=>2, 'code'=>time().mt_rand(100000,999999), 'create_time'=>time(), 'cate2'=>0]);

                // 提交事务
                Db::commit();  
                return json_success(1, '成功');  
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return json_error(0, '失败');
            }

        } else {
            return json_error(0, '数据错误！');
        }
    }

    /**
     * 已结束列表
     * @return [type] [description]
     */
    public function projectorderended()
    {
        $num = 10;
        // 分页
        $page = $this->request->param('page', 1);
        $data = Db::name('project')->alias('p')->join('users u','p.users_id=u.id')->field('p.id,p.title,p.price,p.num,p.status,p.create_time,p.end_time,u.avatarUrl,u.UID')->where('p.status',3)->where('p.users_id',$this->uid)->order('p.create_time desc')->page($page,$num)->select();

        foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
            $count = Db::name('project_order')->where('status', 'in', '1,2,3,5')->where('project_id',$value['id'])->count();
            $data[$key]['browse'] = Db::name('project_browse')->where('project_id',$value['id'])->count();
            $data[$key]['yunum'] =  $value['num'] - $count;       // 剩余数量
            $data[$key]['reserve'] = $count != 0 ? $count*$value['price']:0;
        }
        return json_success(0, '暂停列表', $data);
    }


    /**
     * 官方审核列表
     * @return [type] [description]
     */
    public function projecthe()
    {
        $num = 10;
        // 分页
        $page = $this->request->param('page', 1);
        $data = Db::name('project')->alias('p')->join('users u','p.users_id=u.id')->field('p.id,p.title,p.price,p.num,p.status,p.create_time,p.end_time,u.avatarUrl,u.UID')->where('p.status',0)->where('p.users_id',$this->uid)->order('p.create_time desc')->page($page,$num)->select();

        foreach ($data as $key => $value) {
            $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
            $count = Db::name('project_order')->where('status', 'in', '1,2,3,5')->where('project_id',$value['id'])->count();
            $data[$key]['browse'] = Db::name('project_browse')->where('project_id',$value['id'])->count();
            $data[$key]['yunum'] =  $value['num'] - $count;       // 剩余数量
            $data[$key]['reserve'] = $count != 0 ? $count*$value['price']:0;
        }
        return json_success(0, '官方审核列表', $data);
    }



    /**
     * 提交订单------到稿件
     * @return [type] [description]
     */
    public function suborder()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
        if ($id > 0) {
            $info = Db::name('project_order')->where('id',$id)->find();

            // 1.判断是否在有效时间内提交稿件
            if (strtotime($info['end_time']) <= time()) { 
                Db::name('project_order')->where('id',$id)->update(['status'=>4]);
                return json_error(0, '该任务已超时'); 
            }


            if ($info) {
                $post = $this->request->param();
                $data = [
                    'content'=>$post['content'],
                    'code'=>time().mt_rand(100,999),
                    'project_order_id'=>$id,
                    'users_id'=>$this->uid,
                    'create_time'=>time(),
                    'status'=>1,
                    'no_content'=>'',
                    'image'=>'',
                    'end_time'=>'',
                ];
                if (!empty($data['content'])) {
                    $data['content'] = json_encode($data['content']);
                } else {
                    return json_error(0, '提交内容为空');
                }
                // 启动事务
                Db::startTrans();
                try {
                    Db::name('project_order_examine')->insert($data);    // 提交稿件
                    Db::name('project_order')->where('id',$id)->update(['status'=>2]);   // 修改订单状态
                    // 提交事务
                    Db::commit();
                    return json_success(1, '提交成功');
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return json_error(0, '提交失败');
                }
            } else {
                return json_error(0, '数据错误！');
            }
        } else {
            return json_error(0, '数据错误！');
        }
    }


    /**
     * 审稿件----列表
     * @return [type] [description]
     */
    public function examine()
    {
        $page = $this->request->param('page', 1);
        $status = $this->request->param('status');
        $data = Db::name('project_order')
                ->alias('po')
                ->join('project p','po.project_id=p.id')
                ->join('project_order_examine oe','oe.project_order_id=po.id')
                ->where('oe.status',$status)
                ->where('p.users_id',$this->uid)
                ->field('po.id as order_id,oe.id,po.code as order_code,oe.status,oe.code,oe.content,oe.create_time,oe.no_content,oe.image')
                ->order('oe.create_time desc')
                ->page($page, 10)
                ->select();
        foreach ($data as $key => $value) {
            $data[$key]['create_time'] = date("Y-m-d H:i", $value['create_time']);
            $data[$key]['image'] = explode(',', $value['image']);
        }
        return json_success(1, '待审核', $data);
    }

    /**
     * 稿件数量
     * @param string $token 会员id
     * @return json none 待审核 ended 审核通过 no 审核拒绝
     */
    public function examinenum()
    {
        $data = [
            'none'=>Db::name('project_order')->alias('po')->join('project p','po.project_id=p.id')->join('project_order_examine oe','oe.project_order_id=po.id')->where('oe.status',1)->where('p.users_id',$this->uid)->count(),
            'ended'=>Db::name('project_order')->alias('po')->join('project p','po.project_id=p.id')->join('project_order_examine oe','oe.project_order_id=po.id')->where('oe.status',2)->where('p.users_id',$this->uid)->count(),
            'no'=>Db::name('project_order')->alias('po')->join('project p','po.project_id=p.id')->join('project_order_examine oe','oe.project_order_id=po.id')->where('oe.status',3)->where('p.users_id',$this->uid)->count(),
        ];
        return json_success(1, '稿件数量',$data);
    }

    // /**
    //  * 通过审核
    //  * @return [type] [description]
    //  */
    // public function examinepass()
    // {
    //     $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
    //     if ($id > 0) {
    //         $info = Db::name('project_order_examine')->where('id',$id)->field('project_order_id')->find();
    //         // 获取任务赏金
    //         $price = Db::name('project')->alias('p')->join('project_order po', 'po.project_id=p.id')->where('po.id',$info['project_order_id'])->field('p.users_id,p.price')->find();

    //         Db::startTrans();
    //         try {
    //             // 更改 稿件 状态 为 2 审核通过
    //             Db::name('project_order_examine')->where('id',$id)->update(['status'=>2,'end_time'=>time()]);
    //             // 更改 订单 状态 为 3 已采纳
    //             Db::name('project_order')->where('id',$info['project_order_id'])->update(['status'=>3, 'ended_time'=>time()]);

    //             // 减少雇主的冻结金额
    //             Db::name('users')->where('id', $this->uid)->setDec('frozen_balance',$price['price']);
    //             Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$post['price'], 'cate'=>'4', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>0, 'create_time'=>time()]);  // 会员金额减少记录
    //             // 增加会员金额
    //             Db::name('users')->where('id', $price['users_id'])->setInc('use_balance', $price['price']);

    //             Db::name('users_trend')->insert(['users_id'=>$price['users_id'], 'price'=>$post['price'], 'cate'=>'7', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>1, 'create_time'=>time()]);  // 会员收入记录
    //             // 提交事务
    //             Db::commit();
    //             return json_success(1, '成功');
    //         } catch (\Exception $e) {
    //             // 回滚事务
    //             Db::rollback();
    //             return json_error(0, '失败');
    //         }
    //     } else {
    //         return json_error(0,'数据错误！');
    //     }
    // }


    /**
     * 批量通过审核
     * @return [type] [description]
     */
    public function examinepasses()
    {
        $post = $this->request->post();
        if (isset($post['ids']) && !empty($post['ids'])) {
            $info = Db::name('project_order_examine')->where('id', 'in', $post['ids'])->field('id,project_order_id')->select();
            foreach ($info as $key => $value) {
                // 获取任务赏金
                $price = Db::name('project')->alias('p')->join('project_order po', 'po.project_id=p.id')->where('po.id',$value['project_order_id'])->field('p.users_id,p.price')->find();
                $order = Db::name('project_order')->where('id',$value['project_order_id'])->find();   // 订单信息
              
                $price = Db::name('project')->where('id', $order['project_id'])->find();    // 获取任务信息
                if (!empty($price['share_id'])) {

                    $help = Db::name('share_help')->where('id',$order['share_id'])->find();    // 助力信息 

                    $share = Db::name('share')->where('id',$help['share_id'])->find();   // 分享信息 

                    $activity = Db::name('share_activity')->where('id',$share['activity_id'])->find();    // 获取活动信息

                    // 检测两个任务id 是否相同
                    if ($share['project_id'] == $price['project_id']) {
                        // 检测助力次数  奖励次数  是否已经奖励  是否足够奖励金额  增加分享人的金额 和 记录  
                        $helpcount = Db::name('share_help')->alias('sh')->join('share s', 's.id=sh.share_id')->where('s.activity_id',$activity['id'])->count();  // 当前活动助力次数
                        $helped = Db::name('share_help')->alias('sh')->join('share s', 's.id=sh.share_id')->where('is_help', 1)->where('sh.price', 'not null')->where('s.activity_id',$activity['id'])->count();    // 当前活动奖励 次数
                        // 计算应该奖励次数   总助力次数  除  次数   floor 舍去法取整
                        $num = floor($helpcount/$activity['users_num']);  // 应该奖励次数
                        // 判断奖励次数 小于 奖励次数
                        if ($helped < $num) {
                            // 奖励 当前助力
                            Db::name('share_help')->where('id',$order['share_id'])->update(['is_help'=>1,'price'=>$activity['price'], 'update_time'=>time()]);
                            // 增加会员金额
                            $users = Db::name('users')->where('id',$share['users_id'])->find();
                            Db::name('users')->where('id', $share['users_id'])->update(['use_balance'=>bcadd($users['use_balance'], $activity['price'])]);
                            // 添加金额记录
                            Db::name('users_trend')->insert(['users_id'=>$share['users_id'], 'price'=>$activity['price'], 'cate'=>7, 'status'=>2, 'code'=>time().mt_rand(100000,999999), 'create_time'=>time(), 'cate'=>1, 'message'=>'助力成功奖励', 'vip_id'=>'', 'pay_type'=>'']);
                        }
                    }
                }
                Db::startTrans();
                try {
                    // 更改 稿件 状态 为 2 审核通过
                    Db::name('project_order_examine')->where('id',$value['id'])->update(['status'=>2,'end_time'=>time()]);
                    // 更改 订单 状态 为 3 已采纳
                    Db::name('project_order')->where('id',$value['project_order_id'])->update(['status'=>3, 'ended_time'=>time()]);

                    // 减少雇主的冻结金额
                    Db::name('users')->where('id', $this->uid)->setDec('frozen_balance',$price['price']);
                    Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$price['price'], 'cate'=>'4', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>0, 'create_time'=>time()]);  // 会员金额减少记录
                    // 增加会员金额
                    Db::name('users')->where('id', $price['users_id'])->setInc('use_balance', $price['price']);

                    Db::name('users_trend')->insert(['users_id'=>$price['users_id'], 'price'=>$price['price'], 'cate'=>'7', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>1, 'create_time'=>time()]);  // 会员收入记录
                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return json_error(0,'审核中止');
                }
            }
            return json_success(1, '成功');
        } else {
            return json_error(0,'数据错误！');
        }    
    }


    /**
     * 稿件---不通过
     * @return [type] [description]
     */
    public function examinenot()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
        if ($id > 0) {
            $info = Db::name('project_order_examine')->where('id',$id)->field('project_order_id,users_id')->find();
            $post = $this->request->post();
            $data = [
                'content'=>$post['content'],
                'image'=>$post['image'],
                'status'=>3,
                'end_time'=>time()
            ];
            $validate = new \think\Validate([
                ['content', 'require', '请输入原因'],
            ]);
            if (!$validate->check($data)) {
                return json_error(0,$validate->getError());
            }
            if (!empty($data['image'])) {
                $data['image'] = implode(',', $data['image']);
            }
            Db::startTrans();
            try {
                // 更改 稿件 状态
                Db::name('project_order_examine')->where('id',$id)->update($data);
                // 更改 订单 状态
                Db::name('project_order')->where('id',$info['project_order_id'])->update(['status'=>5, 'ended_time'=>time()]);
                // 添加会员通知
                Db::name('users_notice')->insert(['users_id'=>$info['users_id'], '']);
                // 提交事务
                Db::commit();
                return json_success(1, '成功');
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return json_error(0, '失败');
            }
        } else {
            return json_error(0,'数据错误！');
        }
    }

    /**
     * 修改不采纳的结果内容
     * @return [type] [description]
     */
    public function editexaminenot()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
        $info = Db::name('project_order_examine')->where('id',$id)->find();
        if ($info) {
            $post = $this->request->post();
            if (isset($post['content']) && empty($post['content'])) {
                return json_error(0, '请输入原因');
            }

            $res = Db::name('project_order_examine')->where('id',$id)->update(['no_content'=>$post['content']]);
            if ($res) {
                return json_success(1, '修改成功');
            } else {
                return json_error(0, '修改失败！');
            }
        } else {
            return json_error(0,'数据错误！');
        }
    }

    /**
     * 任务置顶/取消置顶
     * @return [type] [description]
     */
    public function editprojecttop()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
        $info = Db::name('project')->where('id',$id)->find();
        if ($info) {
            if ($info['is_top'] == 'true') {
                $res = Db::name('project')->where('id',$id)->update(['is_top'=>'false']);
                if ($res) {
                    return json_success(1, '取消置顶');
                } else {
                    return json_error(0, '取消失败');
                }
            } else {
                $res = Db::name('project')->where('id',$id)->update(['is_top'=>'true']);
                if ($res) {
                    return json_success(1, '置顶');
                } else {
                    return json_error(0, '置顶失败');
                }
            }
        } else {
            return json_error(0, '数据错误！');
        }
    }

    /**
     * 暂停任务
     * @return [type] [description]
     */
    public function eidtprojectstop()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
        $info = Db::name('project')->where('id',$id)->find();
        if ($info) {
            $res = Db::name('project')->where('id',$id)->update(['is_stop'=>'true','status'=>2]);
            if ($res) {
                return json_success(1, '成功');
            } else {
                return json_error(0, '失败');
            }
        } else {
            return json_error(0, '数据错误！');
        }
    }


    /**
     * 开启任务
     * @return [type] [description]
     */
    public function eidtprojectstart()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
        $info = Db::name('project')->where('id',$id)->find();
        if ($info) {
            $res = Db::name('project')->where('id',$id)->update(['is_stop'=>'false','status'=>1]);
            if ($res) {
                return json_success(1, '成功');
            } else {
                return json_error(0, '失败');
            }
        } else {
            return json_error(0, '数据错误！');
        }
    }


    /**
     * 任务加票--- 暂停状态
     * @return [type] [description]
     */
    public function editprojectaddnum()
    {
        $post = $this->request->post();
        $info = Db::name('project')->where(['id'=>$post['id'],'status'=>2])->find();
        if ($info) {
            if ($info['is_stop'] == 'false') {
                return json_error(0, '请先暂停任务');
            }
            if ($post['num'] <=0) {
                return json_error(0, '请输入加票数量');
            }
            // 获取会员余额
            $use_balance = Db::name('users')->where('id',$this->uid)->value('use_balance');
            $frozen_balance = Db::name('users')->where('id',$this->uid)->value('frozen_balance');
            // 检测账户余额
            $price = bcmul($post['num'], $info['price'], 2);   // 加票所需金额
            if (bcsub($use_balance, $price) < 0) {
                return json_error(0, '账户余额不足，请充值');
            }
            Db::startTrans();
            try {
                // 减少会员余额  添加冻结金额  加票数量  添加金额记录  添加加票记录
                Db::name('users')->where('id',$this->uid)->update(['use_balance'=>bcsub($use_balance, $price)]);
                Db::name('users')->where('id',$this->uid)->update(['frozen_balance'=>bcadd($frozen_balance, $price)]);

                $res = Db::name('project')->where('id',$post['id'])->update(['num'=>bcadd($post['num'], $info['num'])]);
                Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$price, 'cate'=>3, 'status'=>2, 'code'=>time().mt_rand(100000,999999), 'create_time'=>time(), 'cate2'=>0]);
                Db::name('project_editlog')->insert(['users_id'=>$this->uid, 'project_id'=>$post['id'], 'cate'=>0, 'num'=>$post['num'], 'create_time'=>time()]);
                Db::commit();
                return json_success(1,'加票成功');
            } catch (Exception $e) {
                Db::rollback();
                return json_error(0,'加票失败');
            }
        } else {
            return json_error(0, '数据错误！');
        }
    }





    // /**
    //  * 任务加票--- 暂停状态
    //  * @return [type] [description]
    //  */
    // public function editprojectaddnum()
    // {
    //     $id = $this->request->post();
    //     $info = Db::name('project')->where(['id'=>$post['id'],'status'=>2])->find();
    //     if ($info) {
    //         if ($info['is_stop'] == 'false') {
    //             return json_error(0, '请先暂停任务');
    //         }
    //         if ($post['num'] <=0) {
    //             return json_error(0, '请输入加票数量');
    //         }
    //         $res = Db::name('project')->where('id',$post['id'])->setInc('num',$post['num']);
    //         if ($res) {
    //             return json_success(1, '加票成功');
    //         } else {
    //             return json_error(0, '加票失败');
    //         }
    //     } else {
    //         return json_error(0, '数据错误！');
    //     }
    // }


    /**
     * 任务加价--- 暂停状态
     * @return [type] [description]
     */
    public function editprojectaddprice()
    {
        $post = $this->request->post();
        $info = Db::name('project')->where(['id'=>$post['id'],'status'=>2])->find();
        if ($info) {
            if ($info['is_stop'] == 'false') {
                return json_error(0, '请先暂停任务');
            }
            $users = Db::name('users')->where('id',$this->uid)->value('use_balance');
            if ($post['price'] <=0) {
                return json_error(0, '请输入金额');
            } else {
                if (bcsub($users, $post['price'], 2) <=0) {
                    return json_error(0, '余额不足，请重新输入！');
                } else {
                    Db::startTrans();
                    try {
                        Db::name('project')->where('id',$post['id'])->setInc('price',$post['price']);
                        Db::name('users')->where('id',$this->uid)->setInc('frozen_balance',$post['price']);  // 添加冻结金
                        Db::name('users')->where('id',$this->uid)->setDec('use_balance',$post['price']);  // 减少可用金
                        // 提交事务
                        Db::commit();
                        return json_success(1, '加价成功');
                    } catch (\Exception $e) {
                        // 回滚事务
                        Db::rollback();
                        return json_error(0, '加价失败');
                    }
                }
            }
        } else {
            return json_error(0, '数据错误！');
        }
    }


    /**
     * 任务加时--- 暂停状态
     * @return [type] [description]
     */
    public function editprojectaddtime()
    {
        $post = $this->request->post();
        $info = Db::name('project')->where(['id'=>$post['id'],'status'=>2])->find();
        if ($info) {
            if ($info['is_stop'] == 'false') {
                return json_error(0, '请先暂停任务');
            }
            if ($post['time'] <=0) {
                return json_error(0, '请输入时间');
            } else {
                $time = bcmul(3600*24, $post['time'], 0);

                    Db::startTrans();
                    try {
                        Db::name('project')->where('id',$post['id'])->setInc('end_time',$time);
                        // 提交事务
                        Db::commit();
                        return json_success(1, '加时成功');
                    } catch (\Exception $e) {
                        // 回滚事务
                        Db::rollback();
                        return json_error(0, '加时失败');
                    }
            }
        } else {
            return json_error(0, '数据错误！');
        }
    }



}