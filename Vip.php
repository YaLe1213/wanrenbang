<?php

namespace app\api\controller;

use think\Session;
use think\Db;
use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Request; 

/**
 * 会员
 * @package app\api\controller
 */
class Vip extends Controller
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
     * 会员特权页面
     * @return [type] [description]
     */
    public function index()
    {
        
        $data=Db::name('vip_sys')->find();
        $data['nickName']=Db::name('users')->where('id',$this->uid)->value('nickName');
        $data['avatarUrl']=Db::name('users')->where('id',$this->uid)->value('avatarUrl');
        $data['avatarUrl']= addavatarUrl($data['avatarUrl']);
        $data['vip_id']=Db::name('users')->where('id',$this->uid)->value('vip_id');
        if($data['vip_id']>0){
            $data['vip_time']=Db::name('users')->where('id',$this->uid)->value('vip_time');
            $data['vip_time']=date('Y-m-d',$data['vip_time']);
            $data['image']=Db::name('vip')->where('id',$data['vip_id'])->value('image');
        }
        return json_success(1, '会员特权页面', $data);
    }
    //vip 购买页
    public function Vip()
    {
        $data['user']=Db::name('users')->where('id',$this->uid)->field('nickName,avatarUrl,vip_id,vip_time')->find();
        $data['user']['vip_time']=date('Y-m-d',$data['user']['vip_time']);
        $data['user']['avatarUrl']= addavatarUrl($data['user']['avatarUrl']);
        $data['user']['image']=Db::name('vip')->where('id',$data['user']['vip_id'])->value('image');
        $data['vip']=Db::name('vip')->select();
//         foreach ($data['vip'] as $k=>$v){
//             if($v['time_type']==0){
//                 $data['vip']['time_type']='天';
//             }elseif($v['time_type']==1){
//                 $data['vip']['time_type']='月';
//             }elseif($v['time_type']==2){
//                 $data['vip']['time_type']='年';
//             }
//         }
        return json_success(1, 'vip 购买页', $data);
    }
    /**
     * 开通会员 创建订单
     * @return [type] [description]
     */
    public function addorder()
    {
        $id=$this->request->param('id');//会员id
        $vipdetail=Db::name('vip')->where('id',$id)->find();
        if (!$vipdetail) {
            return json_error(0, '数据错误');
        }
        $data=[
            'users_id'=>$this->uid,
            'vip_id'=>$id,
            'price'=>$vipdetail['price'],
            'cate'=>8,
            'status'=>1,
            'code'=>time().rand(111,999),
            'create_time'=>time()
        ];
        $order_id=Db::name('users_trend')->insertGetId($data);
        return json_success(1, '订单创建成功', ['order_id'=>$order_id]);
    }
    /**
     * 开通会员 支付宝支付
     * 
     */
    public function ali_pay()
    {
        $order_id=$this->request->param('id');//订单id
        $order=Db::name('users_trend')->where('id',$order_id)->find();
        //引入sdk
        vendor('alipay-sdk.aop.AopClient');
        vendor('alipay-sdk.aop.request.AlipayTradeAppPayRequest');
        $aop = new \AopClient();
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = "2021000116681657";
        $aop->rsaPrivateKey = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCe42zwdX0Or+Cx56nz2TDlZ68hNJYoE7k1S/WLS4qOqLO8L5ZrH2xtuIe0nLBWOq0ztJkjpDPoSnE54WF+myi+P8TUXKALXQtu+J/fFu+EHqNRjLtFdKtCu/eFoGsD4wCJD3qgSM/bRcv9WTpoaED07Gj2IdzmpegpD6QQOdHxnnntD/paJ8GdKX8ZGF51MVMueogphK60K8ELpGNGQ2geNG2MUF+zvWYdpAGMQVx5U0/I8f2KuC2eti/2r/J3bDj9GEWxggJlcnYG+Jq6oIlRmJuF5PoL5+6kEuK1e/sqIs0WEx/eLjrQfsWIXQHCQcIDKh80C10q8Fi6FWZWTbX3AgMBAAECggEBAI4dx+QwgmIvqGAYYWhuHREkM34U5jYEpkVoosEsGUvO060AN5+rZLWjNyaye/s00pUL9WnuxksAwtPNpwGyULgSm8CC9NgVKlPg6EaH4kafjN81bJAMcd7n99a7DN1WHrV5deqGFf0AKhx6wgZ/MZKhHqUA1vAq90Q67DljLkjZVVb5X0eQLfWm9IHmIHQvNF+/FCxWbe9oW9HJkzzwB9623QcNO9DXu4Qm/CpFWQUhGJLL7OkijdWfuVgdcilu5vIAJo57uvRZx1598eCcXoN1NhX8KzU2kLBd1urYPDeajwPWdcBNQLd8Db8gB3EYIyc8sNvgfAFrVNML2Q6OwhECgYEA6gAwr/k4UR2YNQdXSXnffMB2EWbtnCFuYliDCDf3DxmqJOJMF3R+xte6k8aF7jdSop60nr2j7smcD41AOsX75eADyJcAQdHD53SYnnxIj+TFDHiBrCQ/4S6Q40T10y7ngj4qNo9ot0WtA3xfQd7E+DBk/L9LnUcrybIpp/UCf98CgYEArdN4NfU+Lo8Votv1+cttHxCi5aDFuvj5Z5T6P1gE/j4Xk5Vjrfi3cFJuLB+uSd9q1LS5sBiRdiRdMHHKr08D5z19DdsAmpzS0958IDfM74r8xQDufFOP1ST7vHhbrSIXgCXEElNJhUkx1uQAex9VNABEoBoSWL3ZtljYowSTLOkCgYEAyxn9KFAFLIqmWKiVf4XTj+Ew2WvgItr1h1DR8mk4/BdHkZoFd3o6q3YFUExIZPoJtHJRzVJGnnTJCsqMDDdZqy1ju1As/fQGuLd/3Fd9V7+1tFxIGNShyV50jX6Ga5VThb1VQGP6/M/yGotx1qd3iP/gN2wGQm3KuNb1xv0m/TsCgYAxczt7fi0WabYqApFTYr/EWqM82CPoMPQit4sJgizJdziVz6Xv1BW7anfVLZ4Tfe+SW2eH5TVcerPYGEck4EGoAyIUUv00/vArPdvp/nXan5uRH9a4n70HUeIbl9HcyxoMZrIE1JTRyiTXkT1hyWQfywO62C/n9vp8mYHnvmFwIQKBgC/a2PTDbisqj2f07z70XQoGfJFMI8NuPTQ3XN4/+EALgHGQ42uaZRxfSxtilzlwnXTaHVZ93laPrVVocRL9Q5Ti16Ubv3O8bdi8v/fdXLWOD+AGUVPLxK84PO4RoJkNMOyW5AJBTzKqxRsgcbYqQ/7icYilC613oAJPxY9VAlAw';//请填写开发者私钥去头去尾去回车，一行字符串
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyPb/Z6o513b6lQNIP3NTrqQpIu0c0irH++wojpM2HepXo9NHOXscwMDTLo932wJzsUn3uFDorQPjVh3bvT2ptB1BGTcAsbKOWPF7KzCw7SEHmmcg1fyrQtRzlcU1AhTvsS1s5yotGwsEsngjZLwxo6mQVfipiSaUmfvouAPGgGVe5LAwhCYa1zzMWBi0FG3ZoOg7xuZ64cO18D+grrsSoNlbLZnV8Fa3CizSuyT0IFKmXA0+0FiZYYjBOeAsjrtmtcgP219F19F5f5PZYCPbVAn4qFRQR1N1inQvmlwK2npp2PfjG0cK49pPZnyCTC6tq++7ciuccCMUWb8fbgNFpwIDAQAB';//请填写支付宝公钥，一行字符串
        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new \AlipayTradeAppPayRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $order_name='开通会员';//订单名称，必填
        $order_num=$order['code'];//订单号
        $order_price=$order['price'];//付款金额
        $bizcontent =json_encode(array('subject'=>$order_name,'out_trade_no'=>$order_num,'total_amount'=>$order_price,'timeout_express'=>"30m",'product_code'=>'QUICK_MSECURITY_PAY'));
        $request->setNotifyUrl($this->request->domain()."/index/vip/ali_notify");////异步通知地址
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        //htmlspecialchars是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
        return htmlspecialchars($response);//就是orderString 可以直接给客户端请求，无需再做处理。
    }
    // 支付宝回调
    public function ali_notify()
    {
        //引入sdk 验证回调信息
        vendor('alipay-sdk.aop.AopClient');
        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyPb/Z6o513b6lQNIP3NTrqQpIu0c0irH++wojpM2HepXo9NHOXscwMDTLo932wJzsUn3uFDorQPjVh3bvT2ptB1BGTcAsbKOWPF7KzCw7SEHmmcg1fyrQtRzlcU1AhTvsS1s5yotGwsEsngjZLwxo6mQVfipiSaUmfvouAPGgGVe5LAwhCYa1zzMWBi0FG3ZoOg7xuZ64cO18D+grrsSoNlbLZnV8Fa3CizSuyT0IFKmXA0+0FiZYYjBOeAsjrtmtcgP219F19F5f5PZYCPbVAn4qFRQR1N1inQvmlwK2npp2PfjG0cK49pPZnyCTC6tq++7ciuccCMUWb8fbgNFpwIDAQAB';//请填写支付宝公钥，一行字符串
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        //dump($flag);
        //商户订单号
        $out_trade_no = $_POST['out_trade_no'];
        //支付宝交易号
        $trade_no = $_POST['trade_no'];
        //交易状态
        $trade_status = $_POST['trade_status'];
        //核对支付宝商户号，app_id，支付金额是否对得上，验证传过来的签名是否合法，从而拒绝非法回调
        if($flag){
            //根据返回的订单号做业务逻辑
            $id=Db::name('users_trend')->where('code',$data['out_trade_no'])->value('id');//订单id
            if(Db::name('users_trend')->where('id',$id)->value('status')==0){
                //vip充值
                if(Db::name('users_trend')->where('id',$id)->value('cate')==8){
                    Db::name('users_trend')->where('id',$id)->update(['status'=>1]);
                    $order=Db::name('users_trend')->where('id',$id)->find();
                    $user=Db::name('users')->where('id',$order['users_id'])->find();
                    $vip=Db::name('vip')->where('id',$order['vip_id'])->find();
                    //判断时间类型 转为时间戳
                    if($vip['time_type']==0){
                        //天
                        //                             $time=$vip['time']*86400;
                        $time = strtotime(date("Y-m-d", strtotime("+".$vip['time']." day")).'23:59:59');
                    }elseif($vip['time_type']==2){
                        //月
                        //                             $time=$vip['time']*30*86400;
                        $time = strtotime(date("Y-m-d", strtotime("+".$vip['time']." month")).'23:59:59');
                    }elseif($vip['time_type']==3){
                        //年
                        //                             $time=$vip['time']*12*30*86400;
                        $time = strtotime(date("Y-m-d", strtotime("+".$vip['time']." year")).'23:59:59');
                    }
                    
                    //判断是续费还是开通
                    if($user['vip_id']>0){
                        Db::name('users')->where('id',$user['id'])->update(['vip'=>$vip['id']]);
                        Db::name('users')->where('id',$user['id'])->setInc('score',$time);
                    }else{
                        Db::name('users')->where('id',$user['id'])->update(['vip'=>$vip['id'],'vip_time'=>intval(time()+$time)]);
                    }
                    //赠送置顶时长
                    Db::name('users')->where('id',$user['id'])->setInc('score',$vip['top_time']);
                }
                
                echo "success";//以免支付宝重复回调
            }
            
        }
    }
    
    
    
    
    
    
    /**
     * 开通会员 微信支付
     *
     */
    // 微信支付回调
    public function wx_notify(){
        //接收微信返回的数据数据,返回的xml格式
        $xmlData = file_get_contents('php://input');
        //将xml格式转换为数组
        $data = $this->FromXml($xmlData);
        //用日志记录检查数据是否接受成功，验证成功一次之后，可删除。
        $file = fopen('./log.txt', 'a+');
        fwrite($file,var_export($data,true));
        //为了防止假数据，验证签名是否和返回的一样。
        //记录一下，返回回来的签名，生成签名的时候，必须剔除sign字段。
        $sign = $data['sign'];
        unset($data['sign']);
        if($sign == $this->getSign($data)){
            //签名验证成功后，判断返回微信返回的
            if ($data['result_code'] == 'SUCCESS') {
                //根据返回的订单号做业务逻辑
                $id=Db::name('users_trend')->where('code',$data['out_trade_no'])->value('id');//订单id
                if(Db::name('users_trend')->where('id',$id)->value('status')==0){
                    //vip充值
                    if(Db::name('users_trend')->where('id',$id)->value('cate')==8){
                        Db::name('users_trend')->where('id',$id)->update(['status'=>1]);
                        $order=Db::name('users_trend')->where('id',$id)->find();
                        $user=Db::name('users')->where('id',$order['users_id'])->find();
                        $vip=Db::name('vip')->where('id',$order['vip_id'])->find();
                        //判断时间类型 转为时间戳
                        if($vip['time_type']==0){
                            //天
//                             $time=$vip['time']*86400;
                            $time = strtotime(date("Y-m-d", strtotime("+".$vip['time']." day")).'23:59:59');
                        }elseif($vip['time_type']==2){
                            //月
//                             $time=$vip['time']*30*86400;
                            $time = strtotime(date("Y-m-d", strtotime("+".$vip['time']." month")).'23:59:59');
                        }elseif($vip['time_type']==3){
                            //年
//                             $time=$vip['time']*12*30*86400;
                            $time = strtotime(date("Y-m-d", strtotime("+".$vip['time']." year")).'23:59:59');
                        }
                        
                        //判断是续费还是开通
                        if($user['vip_id']>0){
                            Db::name('users')->where('id',$user['id'])->update(['vip'=>$vip['id']]);
                            Db::name('users')->where('id',$user['id'])->setInc('score',$time);
                        }else{
                            Db::name('users')->where('id',$user['id'])->update(['vip'=>$vip['id'],'vip_time'=>intval(time()+$time)]);
                        }
                        //赠送置顶时长
                        Db::name('users')->where('id',$user['id'])->setInc('score',$vip['top_time']);
                    }
                    
                }
                //处理完成之后，告诉微信成功结果！
                
                    echo '<xml>
              <return_code><![CDATA[SUCCESS]]></return_code>
              <return_msg><![CDATA[OK]]></return_msg>
              </xml>';exit();
               
            }
            //支付失败，输出错误信息
            else{
                $file = fopen('./log.txt', 'a+');
                fwrite($file,"错误信息：".$data['return_msg'].date("Y-m-d H:i:s"),time()."\r\n");
            }
        }
        else{
            $file = fopen('./log.txt', 'a+');
            fwrite($file,"错误信息：签名验证失败".date("Y-m-d H:i:s"),time()."\r\n");
        }
        
    }
    public function wx_pay() {
        $order_id=$this->request->param('id');//订单id
        $order=Db::name('users_trend')->where('id',$order_id)->find();//订单信息
        
        $nonce_str = $this->rand_code();        //调用随机字符串生成方法获取随机字符串
        $data['appid'] ='wxd4aed32966c949cd';   //appid
        $data['mch_id'] = '1605429405' ;        //商户号
        $data['body'] = "APP支付测试";
        $data['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];   //ip地址
        $data['total_fee'] = 1;                         //金额
        $data['out_trade_no'] = time().mt_rand(10000,99999);    //商户订单号,不能重复
        $data['nonce_str'] = $nonce_str;                   //随机字符串
        $data['notify_url'] = $this->request->domain().'/api/vip/wx_notify';   //回调地址,用户接收支付后的通知,必须为能直接访问的网址,不能跟参数
        $data['trade_type'] = 'APP';      //支付方式
        //将参与签名的数据保存到数组  注意：以上几个参数是追加到$data中的，$data中应该同时包含开发文档中要求必填的剔除sign以外的所有数据
        $data['sign'] = $this->getSign($data);        //获取签名
        $xml = $this->ToXml($data);            //数组转xml
        //curl 传递给微信方
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        //header("Content-type:text/xml");
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }    else    {
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
            curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        }
        //设置header
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        //传输文件
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
       
        //返回结果
        if($data){
            curl_close($ch);
            //返回成功,将xml数据转换为数组.
            $re = $this->FromXml($data);
            if($re['return_code'] != 'SUCCESS'){
                return json_error(0, '签名失败');
            }
            else{
                //接收微信返回的数据,传给APP!
                $arr =array(
                    'prepayid' =>$re['prepay_id'],
                    'appid' =>  $re['appid'],
                    'partnerid' => $re['mch_id'],
                    'package' => 'Sign=WXPay',
                    'noncestr' => $nonce_str,
                    'timestamp' =>time(),
                );
                //第二次生成签名
                $sign = $this->getSign($arr);
                $arr['sign'] = $sign;
                return json_success(1, '签名成功', $arr);
            }
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            return json_error(0, 'curl出错，错误码:$error');
        }
    }
    public function FromXml($xml)
    {
        if(!$xml){
            echo "xml数据异常！";
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }
    private function getSign($params) {
        ksort($params);        //将参数数组按照参数名ASCII码从小到大排序
        foreach ($params as $key => $item) {
            if (!empty($item)) {         //剔除参数值为空的参数
                $newArr[] = $key.'='.$item;     // 整合新的参数数组
            }
        }
        $stringA = implode("&", $newArr);         //使用 & 符号连接参数
        $stringSignTemp = $stringA."&key="."huangkelihuangkelihuangkeli77788";        //拼接key

        // key是在商户平台API安全里自己设置的
        $stringSignTemp = MD5($stringSignTemp);       //将字符串进行MD5加密
        $sign = strtoupper($stringSignTemp);      //将所有字符转换为大写
        return $sign;
    }
    
    function rand_code(){
        $str = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';//62个字符
        $str = str_shuffle($str);
        $str = substr($str,0,32);
        return  $str;
    }
    public function ToXml($data=array())
    
    {
        if(!is_array($data) || count($data) <= 0)
        {
            return '数组异常';
        }
        
        $xml = "<xml>";
        foreach ($data as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }

}