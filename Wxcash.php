<?php

namespace app\api\controller;

use think\Session;
use think\Db;
use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Request; 

/**
 * 微信提现
 * @package app\api\controller
 */
class Wxcash extends Controller
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
    
    //创建提现订单
    public function index()
    {

        $price= $this->request->param('price');
        $pay_type= $this->request->param('pay_type');
        if (empty($price) || empty($pay_type)) {
            return json_error(0,'请输入金额');
        }

        $data=[
            'users_id'=>$this->uid,
            'vip_id'=>'',
            'price'=>$price,    
            'cate'=>2,
            'cate2'=>1,
            'status'=>1,
            'code'=>time().rand(111,999),
            'create_time'=>time(),
            'pay_type'=>$pay_type,
        ];
        $use_balance = Db::name('users')->where('id',$this->uid)->value('use_balance');
        if (bcsub($use_balance, $price, 2) < 0) {
            return json_error(0, '申请失败：余额不足');
        }
        Db::startTrans();
        try {
            Db::name('users')->where('id',$this->uid)->setDec('use_balance', $price);
            Db::name('users')->where('id',$this->uid)->setInc('frozen_balance', $price);
            $order_id=Db::name('users_trend')->insertGetId($data);
            Db::commit();
            return json_success(1, '申请成功', ['order_id'=>$order_id]);

        } catch (Exception $e) {
            Db::rollback();
            return json_error(0, '申请失败');
        }
    } 

    // //创建提现订单
    // public function index()
    // {
    //     $user=Db::name('users')->where('id',$this->uid)->find();
    //     // if(empty($user['openid'])){
    //     //    return json_error(0,'openid不存在');
    //     // }
    //     $id=$this->request->param('id');//会员id
    //     $price=Db::name('vip')->where('id',$id)->value('price');
    //     $data=[
    //         'users_id'=>$this->uid,
    //         'vip_id'=>$id,
    //         'price'=>$price,
    //         'cate'=>8,
    //         'status'=>1,
    //         'code'=>time().rand(111,999),
    //         'create_time'=>time()
    //     ];
    //     $order_id=Db::name('users_trend')->insertGetId($data);
    //     return json_success(1, '订单创建成功', ['order_id'=>$order_id]);
    // }
    
    //调起微信提现接口
    public function wxcash($openId,$money)   {
        
        $appid = 'wxd4aed32966c949cd';//商户账号appid
        $mch_id = '1605429405';//商户号
        $key = 'huangkelihuangkelihuangkeli77788';//密钥
        $openid = $user['openid'];//授权用户openid
        
        $arr = array();
        $arr['mch_appid'] = $appid;
        $arr['mchid'] = $mch_id;
        $arr['nonce_str'] = md5(uniqid(microtime(true),true));//随机字符串，不长于32位
        $arr['partner_trade_no'] = '123456789' . date("Ymd") . rand(10000, 90000) . rand(10000, 90000);//商户订单号
        $arr['openid'] = $openid;
        $arr['check_name'] = 'NO_CHECK';//是否验证用户真实姓名，这里不验证
        $arr['amount'] = $money * 100;//付款金额，单位为分
        $arr['desc'] = "零钱提现";//描述信息
        $arr['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];//获取服务器的ip
        //封装的关于签名的算法
        $arr['sign'] = $this->makeSign($arr,$key);//签名
        $var = $this->arrayToXml($arr);
        // dump($arr['sign'] );exit;
        $xml = $this->curl_post_ssl('https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers',$var,30, array(), 1);
        libxml_disable_entity_loader(true);
        //echo $xml; die;
        $obj1=simplexml_load_string($xml,'SimpleXMLElement');
        //var_dump($obj1); die;
        $rdata = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)),true);
        var_dump('cash_xmldata',$rdata);//eblog('cash_xmldata',$rdata);
        // dump($rdata);exit;
        $return_code = trim(strtoupper($rdata['return_code']));
        $result_code = trim(strtoupper($rdata['result_code']));
        if ($return_code == 'SUCCESS' && $result_code == 'SUCCESS') {
            $isrr = array(
                'status'=>1,
                'msg' => '',
            );
        } else {
            // $returnmsg = $rdata['return_msg'];
            $err_code_des = $rdata['err_code_des'];
            $isrr = array(
                'status' => 0,
                'msg' => $err_code_des,
            );
        }
        return $isrr;
    }
    
    protected function makesign($data,$key)
    {
        //获取微信支付秘钥
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = http_build_query($data);
        $string_a = urldecode($string_a);
        //签名步骤二：在string后加入KEY
        //$config=$this->config;
        $string_sign_temp = $string_a."&key=".$key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        $result = strtoupper($sign);
        // $result = strtoupper(hash_hmac("sha256",$string_sign_temp,$key));
        return $result;
    }
    
    protected function arraytoxml($data){
        $str='<xml>';
        foreach($data as $k=>$v) {
            $str.='<'.$k.'>'.$v.'</'.$k.'>';
        }
        $str.='</xml>';
        return $str;
    }
    
    protected function curl_post_ssl($url, $vars, $second = 30, $aHeader = array())
    {
        $isdir = "cert/";//APP_PATH."/common/library/php_sdk/lib/";//证书位置
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);//设置执行最长秒数
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');//证书类型
        curl_setopt($ch, CURLOPT_SSLCERT, $isdir . 'apiclient_cert.pem');//证书位置
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');//CURLOPT_SSLKEY中规定的私钥的加密类型
        curl_setopt($ch, CURLOPT_SSLKEY, $isdir . 'apiclient_key.pem');//证书位置
        curl_setopt($ch, CURLOPT_CAINFO, 'PEM');
        curl_setopt($ch, CURLOPT_CAINFO, $isdir . 'rootca.pem');
        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);//设置头部
        }
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);//全部数据使用HTTP协议中的"POST"操作来发送
        $data = curl_exec($ch);//执行回话
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }
    public function ali_tx()
    {
        
    }
    
        
        




}