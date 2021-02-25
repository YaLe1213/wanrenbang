<?php

namespace app\api\controller;
use think\Request;
use think\Controller;
use think\Db;
use think\Session;
// use app\index\controller\Common;


class Alipay extends Controller{

     
     
    //支付宝公钥
    const alipay_public_key = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAjoW6KdJmzzh5j/3aaRzh3/pRfZXmfuxFfaaCmRLo1J7u0S7oJgIfosq56y0gmu8KEYXQ9l9Y1p6YU0A4id7QzCpGvObcJl2bO5SLDc61jZbP1SWkNB8vPh9cBisCFkMZ0N9XNLiBcJXzsGo2Zl0hfa20GQKWXQNA4JnuCa1BXSkaTBkGMDexXPevp+zGgoCs9jvipX+ldSGiM5QlmUgg/olTAfUFf2QrZSIopfbZKrkotUUXItqzx5iRVGiFa1EUkMSESg+d8zq+WW6Ix7q1yoWmgRPyqUUXYh/yiYLvJDoEZLciB5Bjyh1TPfoy0TpIW2VU1Ytf+XyinFAjQhzAzQIDAQAB";
     
    //商户私钥
    const merchant_private_key = "MIIEogIBAAKCAQEAkvK1CdURIG4K6h7xkssWTZDRRr7Gs8U6/T4nYYQxOCM2DMCIrRLKhwUUEwGDEEhDll00FMv75hzZ2ppi2n+Ubkm9zsMs4FQU/k4adqORUkTNi+9HhLi/w379Jd5ItjgPLdrAwnq61CAaKmt9rc3B6VCGaYoOTCC+hNKUqiHpuJs+fDpNOQM2FFryPE67dCqRq6U64Y6/o8NkVC7CHuMkZv5Ubhi/t+YdM9L/19XZKF14DFhP6SqP+TT7ETWl20mbE+g60HOXw4rn0BEXE+YZAcfIbVf8eLYoveMVXjaT2tqq4I/ftyC2hABVLqJjFgptvald72z3fKaJrfb7GykwXwIDAQABAoIBAFA8Ra2AsUFL1JnRG4ZTbXIcyKggMs6lunfcIBSW4WkNkM2VswsNX5gppbRa9v1E9+J6MZ3JY1laF3nNIny7fIhbq0/iMfaq8muIqdptpUOOXc0ycZJ+bfaIuCCdJoaYRXF8KBQIf0qj3KWc25qy5lZGqZ2my/e1SVGsyY+7xKoPA/bwHgNknneiHGw4UAuRZPzKkObrtuvfSJsYtspyBt1XTtf0URA2npFaJx2im19s64ZbvuR8KM4TZIwhdNqdQqgBXQGIK8au9huJUDi5eq6zJqiPiYDhfBKCUQ5/PHsq+QOfrBlRS1715rv2wUJKfN8mk03UyBzYtrHP49e1aYECgYEA4U18JCNff/vm84a6p0SY0uJb5wKwQupZJ8lt5s57n8KtFvQscBaLKGGFnXwPoDNK0dlQ4qXldl+WRsWu9Xsv8IhsGaigDgJoi21cFgCwO3NZ25R3VbgRiyCFaS3OeCssL2kxj2+bOq9qZHtenNqmoL77gCzWrSQlDV+QEkavkGcCgYEApvg6faYTL72ttpvKP0+qlIEtTOCq4Q4wV7XW/flny3/86DSXmZlm+9x8BnHpLyfzQ8soWuO6N3WXPszxTkAI+VDoUeWSUAPnmzVwwaWn2QiixPypHw1BdDULAPG8N+JzVNgzVYmksvF6mYWrJ9zfXwo3JiCefUVTu00J3dghBUkCgYAa88oGCLVD+j6Go3dwyyP9FlcoK4oqdx5zRAWBtvHTCtbqCAvI3OmIyyHQ1SaJY5lvwS+L7YylvImdrchgVXxGqgtEhoefJWqcQ1jgyPRMKGB3hCtCdeKjYiCTlIc+mOuQQGVNY2yOeK2hl6CZ4w5L7IzpqHOGAuzrzKLPjOUzwQKBgGe/ggik8vMNNy1qbCcex21zPsSwLT2eZWd6s9Yn7NjD9FAMrc5hRV5mSCJxEWWdu0h6qd6f9guT46DAE4h9vZW9Mj4BGgLiCj2k3SVWW48+EHW6URPcVrlwZB/4FO3cpbEoje0uk8okxfsy1YD1e05AuLfWOOS5+Sc+3UydiRcRAoGAfc4dMKv/LRR8U/i1MX05rdc+D6AImO6Tfo9DpJeAt8pFWvfhT45uvpEYl9PUqf2/RlP02aCRafvweyO++3eCWWWg11Fu9QGy7xxJNF3PMIQVTTzoskoG1xZMhnXZniZl3NQszI/n/RK5hxpn5RZnjgVaqQDlG6EBCD9uPONBh6M=";
     
    //支付宝网关
    const gatewayUrl = "https://openapi.alipay.com/gateway.do";
     
    //应用ID
    const app_id = "2021002116627930";
     
    //异步通知地址,只有扫码支付预下单可用
    const notify_url = "";

    public function appPay(Request $req)
    {

        $id = $req->param('id', 1);

        $orderinfo = Db::name('users_trend')->where('id',$id)->find();
        if (!$orderinfo) {
            return json_error(0, '数据错误！');
        }
        if ($orderinfo['cate'] == 1) {    // 充值
            $body = "账户充值";
            $subject="账户充值";
            $out_trade_no = $orderinfo['code'];
            $total_amount = $orderinfo['price'];
        } else if ($orderinfo['cate'] == 8) {    // 开通VIP
            $body = "开通会员";
            $subject="超级商人特权开通";
            $out_trade_no = $orderinfo['code'];
            $total_amount = $orderinfo['price']; 
        }
        // $total_amount = '0.01';
        include_once ('extend/alipay-sdk-PHP/aop/AopClient.php');
        include_once ('extend/alipay-sdk-PHP/aop/request/AlipayTradeAppPayRequest.php');

        $aop = new \AopClient;

        /** 支付宝网关 **/
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';

        /** 应用id,如何获取请参考：https://opensupport.alipay.com/support/helpcenter/190/201602493024 **/
        $aop->appId = '2021002116627930';

        /** 密钥格式为pkcs1，如何获取私钥请参考：https://opensupport.alipay.com/support/helpcenter/207/201602469554  **/
        $aop->rsaPrivateKey = 'MIIEogIBAAKCAQEAkvK1CdURIG4K6h7xkssWTZDRRr7Gs8U6/T4nYYQxOCM2DMCIrRLKhwUUEwGDEEhDll00FMv75hzZ2ppi2n+Ubkm9zsMs4FQU/k4adqORUkTNi+9HhLi/w379Jd5ItjgPLdrAwnq61CAaKmt9rc3B6VCGaYoOTCC+hNKUqiHpuJs+fDpNOQM2FFryPE67dCqRq6U64Y6/o8NkVC7CHuMkZv5Ubhi/t+YdM9L/19XZKF14DFhP6SqP+TT7ETWl20mbE+g60HOXw4rn0BEXE+YZAcfIbVf8eLYoveMVXjaT2tqq4I/ftyC2hABVLqJjFgptvald72z3fKaJrfb7GykwXwIDAQABAoIBAFA8Ra2AsUFL1JnRG4ZTbXIcyKggMs6lunfcIBSW4WkNkM2VswsNX5gppbRa9v1E9+J6MZ3JY1laF3nNIny7fIhbq0/iMfaq8muIqdptpUOOXc0ycZJ+bfaIuCCdJoaYRXF8KBQIf0qj3KWc25qy5lZGqZ2my/e1SVGsyY+7xKoPA/bwHgNknneiHGw4UAuRZPzKkObrtuvfSJsYtspyBt1XTtf0URA2npFaJx2im19s64ZbvuR8KM4TZIwhdNqdQqgBXQGIK8au9huJUDi5eq6zJqiPiYDhfBKCUQ5/PHsq+QOfrBlRS1715rv2wUJKfN8mk03UyBzYtrHP49e1aYECgYEA4U18JCNff/vm84a6p0SY0uJb5wKwQupZJ8lt5s57n8KtFvQscBaLKGGFnXwPoDNK0dlQ4qXldl+WRsWu9Xsv8IhsGaigDgJoi21cFgCwO3NZ25R3VbgRiyCFaS3OeCssL2kxj2+bOq9qZHtenNqmoL77gCzWrSQlDV+QEkavkGcCgYEApvg6faYTL72ttpvKP0+qlIEtTOCq4Q4wV7XW/flny3/86DSXmZlm+9x8BnHpLyfzQ8soWuO6N3WXPszxTkAI+VDoUeWSUAPnmzVwwaWn2QiixPypHw1BdDULAPG8N+JzVNgzVYmksvF6mYWrJ9zfXwo3JiCefUVTu00J3dghBUkCgYAa88oGCLVD+j6Go3dwyyP9FlcoK4oqdx5zRAWBtvHTCtbqCAvI3OmIyyHQ1SaJY5lvwS+L7YylvImdrchgVXxGqgtEhoefJWqcQ1jgyPRMKGB3hCtCdeKjYiCTlIc+mOuQQGVNY2yOeK2hl6CZ4w5L7IzpqHOGAuzrzKLPjOUzwQKBgGe/ggik8vMNNy1qbCcex21zPsSwLT2eZWd6s9Yn7NjD9FAMrc5hRV5mSCJxEWWdu0h6qd6f9guT46DAE4h9vZW9Mj4BGgLiCj2k3SVWW48+EHW6URPcVrlwZB/4FO3cpbEoje0uk8okxfsy1YD1e05AuLfWOOS5+Sc+3UydiRcRAoGAfc4dMKv/LRR8U/i1MX05rdc+D6AImO6Tfo9DpJeAt8pFWvfhT45uvpEYl9PUqf2/RlP02aCRafvweyO++3eCWWWg11Fu9QGy7xxJNF3PMIQVTTzoskoG1xZMhnXZniZl3NQszI/n/RK5hxpn5RZnjgVaqQDlG6EBCD9uPONBh6M=';

        /** 支付宝公钥，如何获取请参考：https://opensupport.alipay.com/support/helpcenter/207/201602487431 **/
        $aop->alipayrsaPublicKey='MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAjoW6KdJmzzh5j/3aaRzh3/pRfZXmfuxFfaaCmRLo1J7u0S7oJgIfosq56y0gmu8KEYXQ9l9Y1p6YU0A4id7QzCpGvObcJl2bO5SLDc61jZbP1SWkNB8vPh9cBisCFkMZ0N9XNLiBcJXzsGo2Zl0hfa20GQKWXQNA4JnuCa1BXSkaTBkGMDexXPevp+zGgoCs9jvipX+ldSGiM5QlmUgg/olTAfUFf2QrZSIopfbZKrkotUUXItqzx5iRVGiFa1EUkMSESg+d8zq+WW6Ix7q1yoWmgRPyqUUXYh/yiYLvJDoEZLciB5Bjyh1TPfoy0TpIW2VU1Ytf+XyinFAjQhzAzQIDAQAB';

        /** 签名算法类型 **/
        $aop->signType = 'RSA2';

        /** 请求使用的编码格式 **/ 
        $aop->postCharset='utf-8';

        /** 仅支持JSON  **/
        $aop->format='json';

        /** 实例化具体API对应的request类，类名称和接口名称对应，当前调用接口名称：alipay.trade.app.pay（app支付接口）**/
        $request = new \AlipayTradeAppPayRequest();

        /** 设置业务参数 **/
        $request->setBizContent("{" .
                                
            /**  商户订单号,商户自定义，需保证在商户端不重复，如：20200612000001 **/                  
            "\"out_trade_no\":\"$out_trade_no\"," .
                                
            /** 销售产品码,固定值：QUICK_MSECURITY_PAY **/                    
            "\"product_code\":\"QUICK_MSECURITY_PAY\"," .
            
            /** 订单金额，精确到小数点后两位 **/
            "\"total_amount\":\"$total_amount\"," .
                                
            /** 订单标题 **/                    
            "\"subject\":\"$subject\"," .
                                
            /** 业务扩展参数 **/
        //  "\"extend_params\":{" .
                      /** 花呗分期参数传值前提：必须有该接口花呗收款准入条件，且需签约花呗分期 **/
                      /** 指定可选期数，只支持3/6/12期，还款期数越长手续费越高 **/
                      // "\"hb_fq_num\":\"3\"," .
                              
                      /** 指定花呗分期手续费承担方式，手续费可以由用户全承担（该值为0），也可以商户全承担（该值为100），但不可以共同承担，即不可取0和100外的其他值。 **/
                      //"\"hb_fq_seller_percent\":\"100\"" .
        //  "}," .
             
            /** 订单描述 **/                   
            "\"body\":\"$body\"" .
        "}");

        /** 异步通知地址，以http或者https开头的，商户外网可以post访问的异步地址，用于接收支付宝返回的支付结果，如果未收到该通知可参考该文档进行确认：https://opensupport.alipay.com/support/helpcenter/193/201602475759 **/
        $request->setNotifyUrl("http://wanrenbang.euku.net/api/alipay/notify");

        /** 调用SDK生成支付链接，可在浏览器打开链接进入支付页面 **/
        $result = $aop->sdkExecute($request); 
        

        return $result;

        /**第三方调用（服务商模式），传值app_auth_token后，会收款至授权token对应商家账号，如何获传值app_auth_token请参考文档：https://opensupport.alipay.com/support/helpcenter/79/201602494631 **/
        //$result = $aop->sdkExecute($request,"传入获取到的app_auth_token值");

        /** response.getBody()打印结果就是orderString，可以直接给客户端请求，无需再做处理。如果传值客户端失败，可根据返回错误信息到该文档寻找排查方案：https://opensupport.alipay.com/support/helpcenter/89 **/
        // return htmlspecialchars($result);

    }



    public function checksign(){
        include_once ('extend/alipay-sdk-PHP/aop/AopClient.php');
        include_once ('extend/alipay-sdk-PHP/aop/request/AlipayTradeAppPayRequest.php');

        $aop = new \AopClient;
        //私钥
        $privatekey="MIIEogIBAAKCAQEAkvK1CdURIG4K6h7xkssWTZDRRr7Gs8U6/T4nYYQxOCM2DMCIrRLKhwUUEwGDEEhDll00FMv75hzZ2ppi2n+Ubkm9zsMs4FQU/k4adqORUkTNi+9HhLi/w379Jd5ItjgPLdrAwnq61CAaKmt9rc3B6VCGaYoOTCC+hNKUqiHpuJs+fDpNOQM2FFryPE67dCqRq6U64Y6/o8NkVC7CHuMkZv5Ubhi/t+YdM9L/19XZKF14DFhP6SqP+TT7ETWl20mbE+g60HOXw4rn0BEXE+YZAcfIbVf8eLYoveMVXjaT2tqq4I/ftyC2hABVLqJjFgptvald72z3fKaJrfb7GykwXwIDAQABAoIBAFA8Ra2AsUFL1JnRG4ZTbXIcyKggMs6lunfcIBSW4WkNkM2VswsNX5gppbRa9v1E9+J6MZ3JY1laF3nNIny7fIhbq0/iMfaq8muIqdptpUOOXc0ycZJ+bfaIuCCdJoaYRXF8KBQIf0qj3KWc25qy5lZGqZ2my/e1SVGsyY+7xKoPA/bwHgNknneiHGw4UAuRZPzKkObrtuvfSJsYtspyBt1XTtf0URA2npFaJx2im19s64ZbvuR8KM4TZIwhdNqdQqgBXQGIK8au9huJUDi5eq6zJqiPiYDhfBKCUQ5/PHsq+QOfrBlRS1715rv2wUJKfN8mk03UyBzYtrHP49e1aYECgYEA4U18JCNff/vm84a6p0SY0uJb5wKwQupZJ8lt5s57n8KtFvQscBaLKGGFnXwPoDNK0dlQ4qXldl+WRsWu9Xsv8IhsGaigDgJoi21cFgCwO3NZ25R3VbgRiyCFaS3OeCssL2kxj2+bOq9qZHtenNqmoL77gCzWrSQlDV+QEkavkGcCgYEApvg6faYTL72ttpvKP0+qlIEtTOCq4Q4wV7XW/flny3/86DSXmZlm+9x8BnHpLyfzQ8soWuO6N3WXPszxTkAI+VDoUeWSUAPnmzVwwaWn2QiixPypHw1BdDULAPG8N+JzVNgzVYmksvF6mYWrJ9zfXwo3JiCefUVTu00J3dghBUkCgYAa88oGCLVD+j6Go3dwyyP9FlcoK4oqdx5zRAWBtvHTCtbqCAvI3OmIyyHQ1SaJY5lvwS+L7YylvImdrchgVXxGqgtEhoefJWqcQ1jgyPRMKGB3hCtCdeKjYiCTlIc+mOuQQGVNY2yOeK2hl6CZ4w5L7IzpqHOGAuzrzKLPjOUzwQKBgGe/ggik8vMNNy1qbCcex21zPsSwLT2eZWd6s9Yn7NjD9FAMrc5hRV5mSCJxEWWdu0h6qd6f9guT46DAE4h9vZW9Mj4BGgLiCj2k3SVWW48+EHW6URPcVrlwZB/4FO3cpbEoje0uk8okxfsy1YD1e05AuLfWOOS5+Sc+3UydiRcRAoGAfc4dMKv/LRR8U/i1MX05rdc+D6AImO6Tfo9DpJeAt8pFWvfhT45uvpEYl9PUqf2/RlP02aCRafvweyO++3eCWWWg11Fu9QGy7xxJNF3PMIQVTTzoskoG1xZMhnXZniZl3NQszI/n/RK5hxpn5RZnjgVaqQDlG6EBCD9uPONBh6M=";
        //签名方式
        $signType="RSA2";
        //待签名字符串（需升序排序处理）
        $data="alipay_sdk=alipay-sdk-php-20200415&app_id=2021002116627930&biz_content=%7B%22body%22%3A%22%D3%E0%B6%EE%B3%E4%D6%B5%22%2C%22subject%22%3A+%22%D3%E0%B6%EE%B3%E4%D6%B5%22%2C%22out_trade_no%22%3A+%2216139863379981%22%2C%22timeout_express%22%3A+%2230m%22%2C%22total_amount%22%3A+%220.01%22%2C%22product_code%22%3A%22QUICK_MSECURITY_PAY%22%7D&charset=GBK&format=json&method=alipay.trade.app.pay&sign_type=RSA2&timestamp=2021-02-22+17%3A32%3A17&version=1.0&sign=eJKEAKHklTE%2Fgi%2FuQvuETe7OyDpClxcSrehPFcdJvcDk7kfOIoD%2FN2oo%2FwF1GsLjbhuKhDQxJ14j545GKTU7Urf2gk%2FTxG5uLrWxWEqGxIKxtc9t4hHaQNb6ZozqBeUVdEp7jCXZ9qt2holkBN7zIKLECiAgLVR3vng9uTY2y4iKNuywWiKTbgjMfOYoOboXEMkLZaE1a8z3NSzAxtiY278O61woqZT3AGTrOr9d21rvNb3l7wqqvLzVTDjkszyhpXqM6y5NPYLZdwePCMsbPNHcqi72Bc583bpGAV4BihSEOTaxb8rLkRclYSK%2BL0%2B61eug%2FVbkGWzxHIbgG9alAg%3D%3D";
        //sdk内封装的签名方法
        $sign=$aop->alonersaSign($data,$privatekey,$signType,false);
        echo "sign:".$sign;
    }

    /**
     * 回调
     * @return [type] [description]
     */
    public function notify()
    {
        /** 
         * alipay_notify.php. 
         * User: lvfk 
         * Date: 2017/10/26 0026 
         * Time: 13:48 
         * Desc: 支付宝支付成功异步通知 
         */  
        // include_once ('extend/alipay-sdk-PHP/aop/AopSdk.php');  
        include_once ('extend/alipay-sdk-PHP/aop/AopClient.php');
        include_once ('extend/alipay-sdk-PHP/aop/request/AlipayTradeAppPayRequest.php');
        //验证签名  服务端验证异步通知信息参数   start
        $aop = new \AopClient();  
        $aop->alipayrsaPublicKey='MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAjoW6KdJmzzh5j/3aaRzh3/pRfZXmfuxFfaaCmRLo1J7u0S7oJgIfosq56y0gmu8KEYXQ9l9Y1p6YU0A4id7QzCpGvObcJl2bO5SLDc61jZbP1SWkNB8vPh9cBisCFkMZ0N9XNLiBcJXzsGo2Zl0hfa20GQKWXQNA4JnuCa1BXSkaTBkGMDexXPevp+zGgoCs9jvipX+ldSGiM5QlmUgg/olTAfUFf2QrZSIopfbZKrkotUUXItqzx5iRVGiFa1EUkMSESg+d8zq+WW6Ix7q1yoWmgRPyqUUXYh/yiYLvJDoEZLciB5Bjyh1TPfoy0TpIW2VU1Ytf+XyinFAjQhzAzQIDAQAB'; 
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");  
          
        // end
        // return 1;
        //验签  
        if($flag){  
            //处理业务，并从$_POST中提取需要的参数内容  
            if($_POST['trade_status'] == 'TRADE_SUCCESS' || $_POST['trade_status'] == 'TRADE_FINISHED'){ //处理交易完成或者支付成功的通知  
                //获取订单号  
                $orderId = $_POST['out_trade_no'];  
                //交易号  
                $trade_no = $_POST['trade_no'];  
                //订单支付时间  
                $gmt_payment = $_POST['gmt_payment'];  
                //转换为时间戳  
                $gtime = strtotime($gmt_payment);  
          
                //此处编写回调处理逻辑  
                $orderinfo = Db::name('users_trend')->where('code',$orderId)->find();
                Db::startTrans();
                try {
                    // 判断是充值还是开通vip
                    if ($orderinfo['cate'] == 2) {
                        Db::name('users')->where('id',$orderinfo['users_id'])->setInc('use_balance',$orderinfo['price']);

                        Db::name('users_trend')->where('code',$orderId)->update(['status'=>2]);
                    } else if ($orderinfo['cate'] == 8) {
                        Db::name('users_trend')->where('code',$orderId)->update(['status'=>2]);
                        // $order=Db::name('users_trend')->where('id',$id)->find();
                        $user=Db::name('users')->where('id',$orderinfo['users_id'])->find();
                        $vip=Db::name('vip')->where('id',$orderinfo['vip_id'])->find();
                        //判断时间类型 转为时间戳
                        if($vip['time_type']==0){
                            //天
                            //                             $time=$vip['time']*86400;
                            $time = strtotime(date("Y-m-d", strtotime("+".$vip['time']." day")).'23:59:59');
                        }elseif($vip['time_type']==1){
                            //月
                            //                             $time=$vip['time']*30*86400;
                            $time = strtotime(date("Y-m-d", strtotime("+".$vip['time']." month")).'23:59:59');
                        }elseif($vip['time_type']==2){
                            //年
                            //                             $time=$vip['time']*12*30*86400;
                            $time = strtotime(date("Y-m-d", strtotime("+".$vip['time']." year")).'23:59:59');
                        }
                        
                      
                        if($user['vip_id']>0){    // 已开通vip  ..升级
                            Db::name('users')->where('id',$user['id'])->update(['vip_id'=>$vip['id']]);
                            Db::name('users')->where('id',$user['id'])->setInc('vip_time',$time);
                        }else{
                            Db::name('users')->where('id',$user['id'])->update(['vip_id'=>$vip['id'],'vip_time'=>intval($time)]);
                        }
                        //赠送置顶时长
                        Db::name('users')->where('id',$user['id'])->setInc('score',$vip['top_time']);
                    }
                        
                    Db::commit();
                    die('success');
                } catch (Exception $e) {
                    Db::rollback();
                    die('fiel');
                }

                //处理成功一定要返回 success 这7个字符组成的字符串，  
                //die('success');//响应success表示业务处理成功，告知支付宝无需在异步通知  
                //处理失败返回field 

            } else {
                 //获取订单号  
                $orderId = $_POST['out_trade_no'];  
                Db::name('users_trend')->where('code',$orderId)->update(['status'=>0]);
                die('success');
            }
        }  
    }


}