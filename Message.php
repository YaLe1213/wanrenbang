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
class Message extends Controller
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

    // /**
    //  * 消息列表
    //  * @return [type] [description]
    //  */
    // public function messagelist()
    // {
    // 	// 所有聊天组
    // 	$data = Db::name('users_private')->whereor('users_id1',$this->uid)->whereor('users_id2',$this->uid)->field('id,users_id1,users_id2')->select();
    // 	$arr = [];
    // 	foreach ($data as $key => $value) {
    // 		if ($value['users_id1'] != $this->uid) {
    // 			$res = Db::name('users')->where('id',$value['users_id1'])->field('id,nickName,avatarUrl,company')->find();
    //             $res['avatarUrl'] = addavatarUrl($res['avatarUrl']);
    // 			$res['private_id'] = $value['id'];
    // 			$res['num'] = Db::name('message')->where(['users_id'=>$value['users_id2'], 'is_look'=>0])->count();
    // 			array_push($arr, $res);
    // 		}
    // 		if ($value['users_id2'] != $this->uid) {
    // 			$res = Db::name('users')->where('id',$value['users_id2'])->field('id,nickName,avatarUrl,company')->find();
    //             $res['avatarUrl'] = addavatarUrl($res['avatarUrl']);
    // 			$res['private_id'] = $value['id'];
    // 			$res['num'] = Db::name('message')->where(['users_id'=>$value['users_id1'], 'is_look'=>0])->count();
    // 			array_push($arr, $res);
    // 		}
    // 	}

    // 	return json_success(1,'数据',$arr);
    // }

    /**
     * 聊天消息列表  -- 一天内 三天内 七天内 半个月内  一个月内
     * @return [type] [description]
     */
    public function messagelist()
    {
        $data = Db::name('message')->where('users_id',$this->uid)->select();
        $arr = [
            'day1'=>[],
            'day3'=>[],
            'day7'=>[],
            'day15'=>[],
            'month'=>[],
        ];
        foreach ($data as $key => $value) {
            $value['avatarUrl'] = Db::name('users')->where('id',$value['users_id'])->value('avatarUrl');
            $value['avatarUrl'] = addavatarUrl($value['avatarUrl']);
            $value['nickName'] = Db::name('users')->where('id',$value['users_id'])->value('nickName');
            $value['year'] = date("Y", $value['create_time']);
            $value['month'] = date("m-d",$value['create_time']);
            $value['title'] = Db::name('project')->where('id',$value['project_id'])->value('title');
            // 今天
            if ($value['create_time'] >= strtotime(date("Y-m-d"))  && $value['create_time'] < strtotime(date("Y-m-d").'23:59:59')) {
                $value['create_time'] = date("Y-m-d H:i",$value['create_time']);
                array_push($arr['day1'], $value);
            }
            // 三天内
            if ($value['create_time'] >= strtotime(date("Y-m-d")) && $value['create_time'] < strtotime(date("Y-m-d", strtotime('-3 day')))) {
                $value['create_time'] = date("Y-m-d H:i",$value['create_time']);
                array_push($arr['day3'], $value);
            }
            // 七天内
            if ($value['create_time'] >= strtotime(date("Y-m-d", strtotime('-3 day'))) && $value['create_time'] < strtotime(date("Y-m-d", strtotime('-7 day')))) {
                $value['create_time'] = date("Y-m-d H:i",$value['create_time']);
                array_push($arr['day7'], $value);
            }
            // 半个月内
            if ($value['create_time'] >= strtotime(date("Y-m-d", strtotime('-7 day'))) && $value['create_time'] < strtotime(date("Y-m-d", strtotime('-15 day')))) {
                $value['create_time'] = date("Y-m-d H:i",$value['create_time']);
                array_push($arr['day15'], $value);
            }
            // 一个月内
            if ($value['create_time'] >= strtotime(date("Y-m-d", strtotime('-15 day'))) && $value['create_time'] < strtotime(date("Y-m-d", strtotime('-1 month')))) {
                $value['create_time'] = date("Y-m-d H:i",$value['create_time']);
                array_push($arr['month'], $value);
            }
        }
        return json_success(1, '消息列表', $arr);
    }
    /**
     * 发送消息
     * @return [type] [description]
     */
    public function release()
    {
        $project_id = $this->request->param('project_id', 1);
        $info = Db::name('project')->where('id',$project_id)->find();
        if ($info) {
            $post = $this->request->param();
            if (empty($post['message']) && empty($post['image'])) {
                return json_error(0, '消息不能为空');
            }
            if (!empty($post['image'])) {
                $post['image'] = implode(',', $post['image']); 
            }

            $res = Db::name('message')->insert(['users_id'=>$this->uid,'message'=>$post['message'],'create_time'=>time(),'project_id'=>$post['project_id'],'is_look'=>0, 'image'=>$post['image']]);
            if ($res) {
                return json_success(1,'发送成功');
            } else {
                return json_error(0,'数据错误！');
            }

        } else {
            return json_error(0, '数据错误！');
        }
    }




    /**
     * 聊天内容
     * @return [type] [description]
     */
    public function getcontent()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
        $res = Db::name('project')->where('id',$id)->find();
        if ($res) {
            $page = $this->request->param('page', 1);
   
            $data = Db::name('message')->alias('m')->join('users u','u.id=m.users_id')->field('u.avatarUrl,u.nickName,m.users_id,m.message,m.image,m.create_time')->where('m.project_id',$id)->order('m.create_time desc')->page($page, 10)->select();

            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $data[$key]['avatarUrl'] = addavatarUrl($value['avatarUrl']);
                }
                $data = arraySort($data, 'create_time');
            }
            
            foreach ($data as $key => $value) {
                $data[$key]['year'] = date("Y-m-d", $value['create_time']);
                $data[$key]['time'] = date('H:i', $value['create_time']);
                $data[$key]['create_time'] = date('Y-m-d H:i', $value['create_time']);
                if ($data[$key]['users_id'] == $this->uid) {
                    $data[$key]['me'] = true;
                } else  {
                    $data[$key]['me'] = false;
                }
            }
            return json_success(1,'聊天内容', $data);
        } else {
            return json_error(0,'数据错误！');
        }
    }

    /**
     * 获取会员信息
     * @return [type] [description]
     */
    public function getusers()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
        $info = Db::name('users')->where('id',$id)->find();
        if (false == $info) {
            return json_error(0, '数据错误！');
        } else {
            $res = Db::name('users_private')->where('users_id1','in',['2',$this->uid])->where('users_id2','in',['2',$this->uid])->value('id');
            if (!empty($res)) {
                $data = [
                    'nickName'=>$info['nickName'],   // 会员昵称
                    'id'=>$res,   //  用于获取聊天信息
                ];
                return json_success(1, '会员信息',$data);
            } else {
                $res3 = Db::name('users_private')->insertGetId(['users_id1'=>$this->uid, 'users_id2'=>$post['id'], 'create_time'=>time()]);
                if ($res3) {
                    $data = [
                        'nickName'=>$info['nickName'],   // 会员昵称
                        'id'=>$res3,   //  用于获取聊天信息
                    ];
                    return json_success(1,'会员信息',$data);
                } else {
                    return json_error(0,'数据错误！');
                }
            }
        }
    }

    // /**
    //  * 获取聊天关键id
    //  * @return [type] [description]
    //  */
    // public function getmessageid()
    // {
    //     $post = $this->request->param();
    //     if (!empty($post['id'])) {
    //         $res = Db::name('users_private')->where('users_id1','in',['2',$this->uid])->where('users_id2','in',['2',$this->uid])->value('id');
    //         if (empty($res)) {
    //             return json_success(1,'success', $res);
    //         } else {
    //             $res3 = Db::name('users_private')->insertGetId(['users_id1'=>$this->uid, 'users_id2'=>$post['id'], 'create_time'=>time()]);
    //             if ($res3) {
    //                 $data['id'] = $res3;
    //                 return json_success(1,'success',$data);
    //             } else {
    //                 return json_error(0,'数据错误！');
    //             }
    //         }
    //     } else {
    //         return json_error(0,'数据错误！');
    //     }
    // }

    // /**
    //  * 发送私聊消息
    //  * @return [type] [description]
    //  */
    // public function release()
    // {
    //     $post = $this->request->post();

    //     $validate = new \think\Validate([
    //         ['private_id', 'require', '数据错误！'],
    //         ['message', 'require', '内容不能为空'],
    //     ]);

    //     if (!$validate->check($post)) {
    //         return json_error(0,$validate->getError());
    //     }
    //     if (empty($post['image'])) {
    //         $post['image'] = '';
    //     }
    //     $res = Db::name('message')->insert(['users_id'=>$this->uid,'message'=>$post['message'],'create_time'=>time(),'users_private_id'=>$post['private_id'],'is_look'=>0, 'image'=>$post['image']]);
    //     if ($res) {
    //         return json_success(1,'发送成功');
    //     } else {
    //         return json_error(0,'数据错误！');
    //     }
    // }


  
    /**
     * 通知消息
     * @return [type] [description]
     */
    public function notice()
    {
        $page = $this->request->has('page') ? $this->request->param('page') : 1 ;
        $num = 20;
        $data['count'] = Db::name('users_notice')->where(['users_id'=>$this->uid, 'is_look'=>0])->count();
        $data['list'] = Db::name('users_notice')->where('users_id',$this->uid)->field('id,title,message,create_time,is_look,tag')->page($page, $num)->order('create_time desc')->select();
        foreach ($data['list'] as $key => $value) {
            $data['list'][$key]['create_time'] = date("Y-m-d H:i",$value['create_time']);
            if (!empty($value['tag'])) {
                $data['list'][$key]['tag'] = explode(',', $value['tag']);
            }
        }
        return json_success(1, '通知消息', $data);
    }

    /**
     * 标记通知消息 为 已读
     * @return [type] [description]
     */
    public function noticeislook()
    {
        $id = $this->request->has('id') ? $this->request->param('id') : 0 ;
        if (false == Db::name('users_notice')->where(['id'=>$id, 'users_id'=>$this->uid])->update(['is_look'=>1])) {
            return json_error(0, '失败');
        } else {
            return json_success(1,'成功');
        }
    }

    /**
     * 消息删除
     * @return [type] [description]
     */
    public function delmessage()
    {
        $id = $this->request->param('id', 1);
        $res = Db::name('message')->where('id', $id)->delete();
        if ($res) {
            return json_success(1, '删除成功');
        } else {
            return json_error(0, '数据错误！');
        }
    }

    public function delets()
    {
        $cate = $this->request->param('cate',1);

        if ($cate == 1) {     // 今天
            $where['create_time'] = ['between', [strtotime(date("Y-m-d")), strtotime(date("Y-m-d").'23:59:59')]];
        } else if ($cate == 2) {        // 三天内
            $where['create_time'] = ['between', [strtotime(date("Y-m-d")), strtotime(date("Y-m-d", strtotime('-3 day')))]];
        } else if ($cate == 3) {        // 七天内
            $where['create_time'] = ['between', [strtotime(date("Y-m-d", strtotime('-3 day'))), strtotime(date("Y-m-d", strtotime('-7 day')))]];
        } else if ($cate == 4) {        // 半个月内
            $where['create_time'] = ['between', [strtotime(date("Y-m-d", strtotime('-7 day'))), strtotime(date("Y-m-d", strtotime('-15 day')))]];
        } else if ($cate == 5) {        // 一个月内
            $where['create_time'] = ['between', [strtotime(date("Y-m-d", strtotime('-15 day'))), strtotime(date("Y-m-d", strtotime('-1 month')))]];
        } else {
            return json_error(0, '数据错误！');
        }

        $where['users_id'] = $this->uid;
        $res = Db::name('message')->where($where)->delete();
        if ($res) {
            return json_success(1, '清空成功');
        } else {
            return json_error(0, '清空失败');
        }
    }

}