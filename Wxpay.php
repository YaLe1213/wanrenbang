<?php

namespace app\api\controller;
use think\Request;
use think\Controller;
use think\Db;
use think\Session;
// use app\index\controller\Common;
/**
* 2020.09.05
*
*
*
*/
/*
 * 小程序微信支付
*/


// 商户号 1603039973
// 网站 www.hmznsc.com   
// appid wxf4b44f28d5ce5274
// 秘钥 j57dk99gr1wupsmuyl6gjsgnzv7646rv
class Wxpay extends Controller{
    protected $appid;
    protected $mch_id;//商户号
    protected $key;
    protected $openid; //用户id
    protected $out_trade_no; //$out_trade_no;//订单号
    protected $body;//自定义商品描述
    protected $total_fee; //金额
    protected $notify_url; //异步通知地址
    protected $trade_type; //交易类型
    protected $config;
    //spbill_create_ip ip地址    
    //微信异步通知地址
    // public function notify(Request $re){
    //     $arr=$_POST;
    //     Db('text')->insert(['text'=>json_encode($arr)]);
    // }

    public function _initialize(){
        
    }
    function get_client_ip($type = 0, $adv = true) {
        $type       =  $type ? 1 : 0;
        static $ip  =   NULL;
        if ($ip !== NULL) return $ip[$type];
        if($adv){
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos    =   array_search('unknown',$arr);
                if(false !== $pos) unset($arr[$pos]);
                $ip     =   trim($arr[0]);
            }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip     =   $_SERVER['HTTP_CLIENT_IP'];
            }elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip     =   $_SERVER['REMOTE_ADDR'];
            }
        }elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u",ip2long($ip));
        $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }

    public function pay(Request $re) {
        $id=$re->param('id');
        $order=Db('user_order')->where('id',$id)->field('number')->find();
        $details=Db('user_order_details')->where('order_id',$id)->field('price,num')->select();
        $price=0;
        foreach($details as $k=>$v){
            $price=bcadd($price,bcmul($v['price'],$v['num'],2),2);
        }
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $str=$this->createNoncestr();
        $parameters=Db('wxpay_config')->where('id',1)->field('appid,mch_id,body,notify_url,trade_type')->find();
        $parameters['nonce_str']=$str;//32位随机字符串
        $parameters['out_trade_no']=$order['number'];//订单号
        $parameters['total_fee']=$price*100;//总金额 单位分
        $parameters['spbill_create_ip']=$this->get_client_ip();//用户ip地址
        $key=Db('wxpay_config')->where('id',1)->field('key')->find();

        $parameters['sign']=$this->getSign($parameters,$key['key']);
        // return json($parameters);
        // print_R($parameters);
        $xmlData = $this->arrayToXml($parameters);
        // return json($xmlData);
        $a=$this->postXmlCurl($xmlData, $url, 60);
        // return json($a);
        $return = $this->xmlToArray($a);
        if(!isset($return['mweb_url'])){
            $this->error('系统错误');
        }
        $this->assign('price',$price);
        $this->assign('id',$id);
        $this->assign('url',$return['mweb_url'].'&redirect_url='.urlencode('http://www.hmznsc.com/index/store.wxpay/res?id='.$id));
        return view('index');
        
    }

    public function g_pay(Request $re){
        $id=$re->param('id');

        $order=Db('users_trend')->where('id',$id)->find();
            
        $price=$order['price'];
        $name= '充值';

        $openid=Db('users')->where('id',$order['users_id'])->value('openid');
        // return json($openid);
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $config=Db('wxpay_config')->where('id',1)->find();

        $str=$this->createNoncestr();
        $parameters = array(
            'appid' => $config['appid'], //小程序ID
            'mch_id' => $config['mch_id'], //商户号
            'nonce_str' => $str, //随机字符串
            'body' => $config['body'], //商品描述
            'out_trade_no'=> $order['code'],//订单号
            'total_fee' =>0.01*100,//$total_fee*100, //金额
            'spbill_create_ip' => $this->get_client_ip(), //终端IP
            'notify_url' => $config['notify_url'], //通知地址  确保外网能正常访问
            'openid' => $openid, //用户id
            'trade_type' => 'JSAPI',//交易类型
        );
        // return json($parameters);
        //统一下单签名
        $parameters['sign'] = $this->getSign($parameters,$config['key']); //生成签名
        $xmlData = $this->arrayToXml($parameters);
        // return json($xmlData);
        $a=$this->postXmlCurl($xmlData, $url, 60);
        $return = $this->xmlToArray($a);
        // return json($return);
        if($return['return_msg']=='OK'){
            $parameters = array(
                'appId' => $config['appid'], //小程序ID
                'timeStamp' => '"'.time().'"', //时间戳
                'nonceStr' => $str, //随机串
                'package' => 'prepay_id='.$return['prepay_id'], //数据包
                'signType' => 'MD5'//签名方式
            );
            //签名
            $parameters['paySign'] = $this->getSign($parameters,$config['key']);

            // $return = $this->weixinapp($data['ip'],$appid,$data['id']);
            return json(['status'=>'success','msg'=>'','data'=>$parameters]);
        }else{
            return json(['status'=>'error']);
        }
    }
    public function api(){
        $echoStr = $_GET["echostr"];
        echo $echoStr;
        exit;
    }

    private static function postXmlCurl($xml, $url, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        set_time_limit(0);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new WxPayException("curl出错，错误码:$error");
        }
    }

    //xml转换成数组
    private function xmlToArray($xml) {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xmlstring), true);
        return $val;
    }
    
    //数组转换成xml
    private function arrayToXml($arr) {
        $xml = "<root>";
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml .= "<" . $key . ">" . arrayToXml($val) . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }
        }
        $xml .= "</root>";
        return $xml;
    }
    //作用：格式化参数，签名过程需要使用
    private function formatBizQueryParaMap($paraMap, $urlencode) {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar;
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }
    
    //作用：生成签名
    private function getSign($Obj,$AppSecret) {
        foreach ($Obj as $k => $v) {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $AppSecret;
        //签名步骤三：MD5加密
        $String = md5($String);
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        return $result_;
    }
    //作用：产生随机字符串，不长于32位
    private function createNoncestr($length = 32) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public function notify()
    {
        $postXml = file_get_contents("php://input");    // 接受通知参数；
        $data = $this->FromXml($postXml);

        
        file_put_contents ( "./wxpay.log", date ( "Y-m-d H:i:s" ) . "  " . var_export($data,true) . "\r\n", FILE_APPEND );

        $out_trade_no = $data['out_trade_no'];

        $order=Db('users_trend')->where('code',$out_trade_no)->find();
        $course = Db::name('course')->where('id',$order['course_id'])->value('present_price');
        if($data["return_code"] == "SUCCESS" && $data["result_code"] == "SUCCESS"){


            if($order['status'] == 1){
                Db::startTrans();
                try {
                    Db::name('users_trend')->where('code',$out_trade_no)->update(['status'=>2]);
                    Db::name('users')->where('id', $order['users_id'])->setInc('use_balance', $order['price']);
                    Db::commit();
                    return true;
                } catch (Exception $e) {
                    Db::rollback();
                    return false;
                }
                
            }
        } else {
            // 修改订单状态
            Db::name('users_trend')->where('code',$out_trade_no)->update(['status'=>0]);
            return true;
        }
    }

    public function res(){
        $orderdata = $_GET;
        $order=Db('user_order')->where('id',$orderdata['id'])->find();
        if($order){
            if($order['status']==1){
                $this->redirect('index/user.order/details?id='.$order['id'].'&msg=支付成功1');
            }else{
                $this->redirect('index/user.order/details?id='.$order['id'].'&msg=支付失败2');
            }
        }else{
            $this->redirect('index/user.order/details?id='.$order['id'].'&msg=支付失败1');
        }
    }

    //xml格式转object
    public function xmlToObject($xmlStr) {
        if (!is_string($xmlStr) || empty($xmlStr)) {
            return false;
        }
        // 由于解析xml的时候，即使被解析的变量为空，依然不会报错，会返回一个空的对象，所以，我们这里做了处理，当被解析的变量不是字符串，或者该变量为空，直接返回false
        $postObj = simplexml_load_string($xmlStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        $postObj = json_decode(json_encode($postObj));
        //将xml数据转换成对象返回
        return $postObj;
    }
    //xml转数组
    public function FromXml($xml)
    {
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $this->values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $this->values;
    }

}