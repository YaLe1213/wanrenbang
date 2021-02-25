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
class Bond extends Controller
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
     * 我的保证金
     * @return [type] [description]
     */
    public function getbondbalance()
    {
        
        $data = Db::name('users')->where('id',$this->uid)->field('bond_balance')->find();
        return json_success(1, '保证金', $data);
    }

    /**
     * 充值保证金
     * @return [type] [description]
     */
    public function addbond()
    {
        $post['price'] = $this->request->post('price');
        $validate = new \think\Validate([
            ['price', 'require', '请输入充值金额'],
        ]);
        if (!$validate->check($post)) {
            return json_error(0,$validate->getError());
        }
        $bond =Db::name('users')->where('id',$this->uid)->value('use_balance'); 
        $low_bond = Db::name('app_config')->where('id',1)->value('low_bond');   // 最低保证金
        if ($post['price'] < $low_bond) {
            return json_error(0, '充值数量不能少于'.$low_bond);
        }
        if (bcsub($bond, $post['price'], 2) < 0 ) {
            return json_error(0, '账号余额不足');
        }
        // 启动事务
        Db::startTrans();
        try {
            Db::name('users')->where('id',$this->uid)->setDec('use_balance',$post['price']);   // 减少余额
            Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$post['price'], 'cate'=>'6', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>1, 'create_time'=>time()]);
            Db::name('users')->where('id',$this->uid)->setInc('bond_balance',$post['price']);  // 增加保证金
            Db::name('users_bond')->insert(['name'=>'充值保证金', 'price'=>$post['price'], 'cate'=>1, 'create_time'=>time()]);// 添加保证金充值记录
            Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$post['price'], 'cate'=>'4', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>1, 'create_time'=>time()]);
            // 提交事务
            Db::commit();
            return json_success(1, '充值成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json_error(0, '充值失败');
            // 　　$e->getMessage();
        }
    }

    /**
     
     * @return [type] * 提取保证金[description]
     */
    public function reducebond()
    {
        $bond =Db::name('users')->where('id',$this->uid)->field('use_balance,bond_balance')->find(); 
        if ($bond['bond_balance'] <= 0 ) {
            return json_error(0, '没有保证金申请');
        }
        $before = bcadd($bond['bond_balance'], $bond['use_balance'], 2);
        $res = Db::name('users')->where('id',$this->uid)->update(['bond_balance'=>0]);
        if ($res) {
            Db::name('users')->where('id',$this->uid)->update(['use_balance'=>$before]);  // 更新会员可用金额

            Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$before, 'cate'=>'7', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>1, 'create_time'=>time()]);

            Db::name('users_bond')->insert(['name'=>'申请退还保证金', 'price'=>$before, 'cate'=>0, 'create_time'=>time()]);// 添加保证金充值记录
            Db::name('users_trend')->insert(['users_id'=>$this->uid, 'price'=>$before, 'cate'=>'4', 'status'=>'2', 'code'=>time().mt_rand(1000,9999), 'cate2'=>0, 'create_time'=>time()]);
            return json_success(1, '提取成功');
        } else {
            return json_error(0, '提取失败');
        }
    }

}