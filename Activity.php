<?php

namespace app\api\controller;

use think\Session;
use think\Db;
use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Request; 

/**
 * 收徒奖励 师门大赛
 * @package app\api\controller
 */
class Activity extends Controller
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

    
    // 师门大赛活动页面
    public function index()
    {
        
        $data['activity']=Db::name('activity')->field('end_time,title,price')->find();
        $data['activity']['end_time']=$data['activity']['end_time']-time();
        $data['ling']=Db::name('activity_users')->field('sum_price,dai_price,today_price,prop')->where('users_id',$this->uid)->find();
        $data['paihang']=Db::name('activity_users')->alias('a')->join('users u','a.users_id=u.id')->field('u.nickName,a.sum_price,a.jiangjin')->select();
        $data['user']=Db::name('users')->where('id',$this->uid)->field('id,nickName,avatarUrl')->find();
        $data['user']['avatarUrl'] = addavatarUrl($data['user']['avatarUrl']);
        $data['user']['sum_price']=$data['ling']['sum_price'];
        $data['user']['paiming']=Db::name('activity_users')->where('users_id',$this->uid)->value('paiming');
        return json_success(1, '师门大赛', $data);
    }
    //排行榜
    public function paihang()
    {
        $page=$this->request->param('page');
        $list['list']=Db::name('activity_users')->alias('a')->join('users u','a.users_id=u.id')->page($page.',10')->field('u.nickName,a.sum_price,a.jiangjin')->select();
        $list['count']=Db::name('activity_users')->alias('a')->join('users u','a.users_id=u.id')->count();
        $list['count']=ceil($list['count']/10);
        return json_success(1, '排行榜', $list);
    }
    //收徒奖励页面
    public function apprentice()
    {
        //进贡奖励参数
        $data=Db::name('apprentice_config')->find();
        //我的徒弟徒孙
        $list['tudi']=Db::name('users')->where('pid',$this->uid)->field('id,nickName,avatarUrl,phone,create_time')->select();
        $list['tusun']=[];
        $list['quanbu']=[];
        foreach ($list['tudi'] as $key=>$val){
            array_push($list['quanbu'], $val);
            $user=Db::name('users')->where('pid',$val['id'])->field('id,nickName,avatarUrl,phone,create_time')->select();
            if(!empty($user)){
                foreach ($user as $k=>$v){
                    array_push($list['tusun'], $v);
                    array_push($list['quanbu'], $v);
                }
            }
        }
        $data['num']=count($list['quanbu']);
        $data['yiyaoqing']=Db::name('users')->where('pid',$this->uid)->count();
        $data['yiwancheng']=Db::name('project_order')->where('users_id',$this->uid)->where('status','3')->count();
        $data['yizhuan']=0;
        $data['daizhuan']=0;
        return json_success(1, '收徒奖励页面', $data);
    }
    
    
    
    
    
    
    
    
    //徒弟列表
    public function tudi()
    {
        $list['tudi']=Db::name('users')->where('pid',$this->uid)->field('id,nickName,avatarUrl,phone,create_time')->select();
        $list['tusun']=[];
        $list['quanbu']=[];
        foreach ($list['tudi'] as $key=>$val){
            array_push($list['quanbu'], $val);
            $user=Db::name('users')->where('pid',$val['id'])->field('id,nickName,avatarUrl,phone,create_time')->select();
            if(!empty($user)){
                foreach ($user as $k=>$v){
                    array_push($list['tusun'], $v);
                    array_push($list['quanbu'], $v);
                }
            }
        }
        return json_success(1, '徒弟列表', $list);
    }


}