<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
use Firebase\JWT\JWT;
/**
 * 敏感词过滤
 *
 * @param  string
 * @return string
 */
function sensitive_words_filter($str)
{
    if (!$str) return '';
    $file = ROOT_PATH. PUBILC_PATH.'/static/plug/censorwords/CensorWords';
    $words = file($file);
    foreach($words as $word)
    {
        $word = str_replace(array("\r\n","\r","\n","/","<",">","="," "), '', $word);
        if (!$word) continue;

        $ret = preg_match("/$word/", $str, $match);
        if ($ret) {
            return $match[0];
        }
    }
    return '';
}

/**
 * 上传路径转化,默认路径 UPLOAD_PATH
 * $type 类型
 */
function makePathToUrl($path,$type = 2)
{
    $path =  DS.ltrim(rtrim($path));
    switch ($type){
        case 1:
            $path .= DS.date('Y');
            break;
        case 2:
            $path .=  DS.date('Y').DS.date('m');
            break;
        case 3:
            $path .=  DS.date('Y').DS.date('m').DS.date('d');
            break;
    }
    if (is_dir(ROOT_PATH.UPLOAD_PATH.$path) == true || mkdir(ROOT_PATH.UPLOAD_PATH.$path, 0777, true) == true) {
        return trim(str_replace(DS, '/',UPLOAD_PATH.$path),'.');
    }else return '';

}

// 过滤掉emoji表情
function filterEmoji($str)
{
    $str = preg_replace_callback(    //执行一个正则表达式搜索并且使用一个回调进行替换
        '/./u',
        function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        },
        $str);
    return $str;
}

//可逆加密
// function encrypt($data, $key) {
//     $prep_code = serialize($data);
//     $block = mcrypt_get_block_size('des', 'ecb');
//     if (($pad = $block - (strlen($prep_code) % $block)) < $block) {
//         $prep_code .= str_repeat(chr($pad), $pad);
//     }
//     $encrypt = mcrypt_encrypt(MCRYPT_DES, $key, $prep_code, MCRYPT_MODE_ECB);
//     return base64_encode($encrypt);
// }

//可逆解密
// function decrypt($str, $key) {
//     $str = base64_decode($str);
//     $str = mcrypt_decrypt(MCRYPT_DES, $key, $str, MCRYPT_MODE_ECB);
//     $block = mcrypt_get_block_size('des', 'ecb');
//     $pad = ord($str[($len = strlen($str)) - 1]);
//     if ($pad && $pad < $block && preg_match('/' . chr($pad) . '{' . $pad . '}$/', $str)) {
//         $str = substr($str, 0, strlen($str) - $pad);
//     }
//     return unserialize($str);
// }
//替换一部分字符
/**
 * @param $string 需要替换的字符串
 * @param $start 开始的保留几位
 * @param $end 最后保留几位
 * @return string
 */
function strReplace($string,$start,$end)
{
    $strlen = mb_strlen($string, 'UTF-8');//获取字符串长度
    $firstStr = mb_substr($string, 0, $start,'UTF-8');//获取第一位
    $lastStr = mb_substr($string, -1, $end, 'UTF-8');//获取最后一位
    return $strlen == 2 ? $firstStr . str_repeat('*', mb_strlen($string, 'utf-8') -1) : $firstStr . str_repeat("*", $strlen - 2) . $lastStr;

}


/**
 * 发送HTTP请求方法
 * @param  string $url    请求URL
 * @param  array  $params 请求参数
 * @param  string $method 请求方法GET/POST
 * @return array  $data   响应数据
 */
function httpCurl($url, $params, $method = 'POST', $header = array(), $multi = false){
    date_default_timezone_set('PRC');
    $opts = array(
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => $header,
        CURLOPT_COOKIESESSION  => true,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_COOKIE         =>session_name().'='.session_id(),
    );
    /* 根据请求类型设置特定参数 */
    switch(strtoupper($method)){
        case 'GET':
            // $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
            // 链接后拼接参数  &  非？
            $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
            break;
        case 'POST':
            //判断是否传输文件
            $params = $multi ? $params : http_build_query($params);
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_POST] = 1;
            $opts[CURLOPT_POSTFIELDS] = $params;
            break;
        default:
            throw new Exception('不支持的请求方式！');
    }
    /* 初始化并执行curl请求 */
    $ch = curl_init();
    curl_setopt_array($ch, $opts);
    $data  = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if($error) throw new Exception('请求发生错误：' . $error);
    return  $data;
}
/**
 * 微信信息解密
 * @param  string  $appid  小程序id
 * @param  string  $sessionKey 小程序密钥
 * @param  string  $encryptedData 在小程序中获取的encryptedData
 * @param  string  $iv 在小程序中获取的iv
 * @return array 解密后的数组
 */
function decryptData( $appid , $sessionKey, $encryptedData, $iv ){
    $OK = 0;
    $IllegalAesKey = -41001;
    $IllegalIv = -41002;
    $IllegalBuffer = -41003;
    $DecodeBase64Error = -41004;
 
    if (strlen($sessionKey) != 24) {
        return $IllegalAesKey;
    }
    $aesKey=base64_decode($sessionKey);
 
    if (strlen($iv) != 24) {
        return $IllegalIv;
    }
    $aesIV=base64_decode($iv);
 
    $aesCipher=base64_decode($encryptedData);
 
    $result=openssl_decrypt( $aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
    $dataObj=json_decode( $result );
    if( $dataObj  == NULL )
    {
        return $IllegalBuffer;
    }
    if( $dataObj->watermark->appid != $appid )
    {
        return $DecodeBase64Error;
    }
    $data = json_decode($result,true);
 
    return $data;
}
 

function define_str_replace($data)
{
    return str_replace(' ','+',$data);
}

/**
 * 创建 token
 * @param array $data 必填 自定义参数数组
 * @param integer $exp_time 必填 token过期时间 单位:秒 例子：7200=2小时
 * @param string $scopes 选填 token标识，请求接口的token
 * @return string
 */
function createToken($data = "", $exp_time = 0, $scopes = "")
{

    //JWT标准规定的声明，但不是必须填写的；
    //iss: jwt签发者
    //sub: jwt所面向的用户
    //aud: 接收jwt的一方
    //exp: jwt的过期时间，过期时间必须要大于签发时间
    //nbf: 定义在什么时间之前，某个时间点后才能访问
    //iat: jwt的签发时间
    //jti: jwt的唯一身份标识，主要用来作为一次性token。
    //公用信息
    try {
        $key = 'release';
        $time = time(); //当前时间
        $token['iss'] = 'Jouzeyu'; //签发者 可选
        $token['aud'] = ''; //接收该JWT的一方，可选
        $token['iat'] = $time; //签发时间
        $token['nbf'] = $time+3; //(Not Before)：某个时间点后才能访问，比如设置time+30，表示当前时间30秒后才能使用
        if ($scopes) {
            $token['scopes'] = $scopes; //token标识，请求接口的token
        }
        if (!$exp_time) {
            $exp_time = 3600*24*30;//5分钟过期
        }
        $token['exp'] = $time + $exp_time; //token过期时间,这里设置2个小时
        if ($data) {
            $token['uid'] = $data; //自定义参数
        }

        $json = JWT::encode($token, $key);
        //Header("HTTP/1.1 201 Created");
        //return json_encode($json); //返回给客户端token信息
        return $json; //返回给客户端token信息

    } catch (\Firebase\JWT\ExpiredException $e) {  //签名不正确
        $returndata['code'] = "104";//101=签名不正确
        $returndata['msg'] = $e->getMessage();
        $returndata['data'] = "";//返回的数据
        return json_encode($returndata); //返回信息
    } catch (Exception $e) {  //其他错误
        $returndata['code'] = "199";//199=签名不正确
        $returndata['msg'] = $e->getMessage();
        $returndata['data'] = "";//返回的数据
        return json_encode($returndata); //返回信息
    }
}

/**
 * 验证token是否有效,默认验证exp,nbf,iat时间
 * @param string $jwt 需要验证的token
 * @return string $msg 返回消息
 */
function checkToken($jwt)
{
    $key = 'release';
    try {
        JWT::$leeway = 60;//当前时间减去60，把时间留点余地
        $decoded = JWT::decode($jwt, $key, ['HS256']); //HS256方式，这里要和签发的时候对应
        $arr = (array)$decoded;

        $returndata['code'] = "200";//200=成功
        $returndata['msg'] = "成功";//
        $returndata['data'] = $arr;//返回的数据
        return json_encode($returndata,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); //返回信息

    } catch (\Firebase\JWT\SignatureInvalidException $e) {  //签名不正确
        //echo "2,";
        //echo $e->getMessage();
        $returndata['code'] = "101";//101=签名不正确
        $returndata['msg'] = $e->getMessage();
        $returndata['data'] = "";//返回的数据
        return json_encode($returndata); //返回信息
    } catch (\Firebase\JWT\BeforeValidException $e) {  // 签名在某个时间点之后才能用
        //echo "3,";
        //echo $e->getMessage();
        $returndata['code'] = "102";//102=签名不正确
        $returndata['msg'] = $e->getMessage();
        $returndata['data'] = "";//返回的数据
        return json_encode($returndata); //返回信息
    } catch (\Firebase\JWT\ExpiredException $e) {  // token过期
        //echo "4,";
        //echo $e->getMessage();
        $returndata['code'] = "103";//103=签名不正确
        $returndata['msg'] = $e->getMessage();
        $returndata['data'] = "";//返回的数据
        return json_encode($returndata); //返回信息
    } catch (Exception $e) {  //其他错误
        //echo "5,";
        //echo $e->getMessage();
        $returndata['code'] = "199";//199=签名不正确
        $returndata['msg'] = $e->getMessage();
        $returndata['data'] = "";//返回的数据
        return json_encode($returndata); //返回信息
    }
    //Firebase定义了多个 throw new，我们可以捕获多个catch来定义问题，catch加入自己的业务，比如token过期可以用当前Token刷新一个新Token
}

// 解密token
 function check($token){
    // vendor('firebase.php-jwt.src.JWT');
    $JWT=new \Firebase\JWT\JWT();
     $jwt = $token;
   // $jwt = input("token");  //上一步中返回给用户的token
    $key = "release";  //上一个方法中的 $key 本应该配置在 config文件中的
    $info = $JWT->decode($jwt,$key,["HS256"]); //解密jwt
    return $info;
}
/**
 * 成功返回
 * @param  int $code 成功码
 * @param  [type] $msg  [description]
 * @param  array  $arr  [description]
 * @return [type]       [description]
 */
function json_success($code,$msg,$arr=[]){
    return json_encode(['code'=>$code,'status_code'=>'success','msg'=>$msg,'datas'=>$arr],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
}
/**
 * 失败返回
 * @param  int $code 错误码
 * @param  string $msg  提示信息
 * @param  array  $arr  返回数据
 * @return [type]       [description]
 */
function json_error($code,$msg,$arr=[]){
    return json_encode(['code'=>$code,'status_code'=>'error','msg'=>$msg,'datas'=>$arr],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
}

/**
 * 二维数组根据某个字段排序
 * @param array $array 要排序的数组
 * @param string $keys   要排序的键字段
 * @param string $sort  排序类型  SORT_ASC     SORT_DESC 
 * @return array 排序后的数组
 */
function arraySort($array, $keys, $sort = SORT_DESC) {
    $keysValue = [];
    foreach ($array as $k => $v) {
        $keysValue[$k] = $v[$keys];
    }
    array_multisort($keysValue, $sort, $array);
    return $array;
}
/**
 * 格式化时间
 * @param  [type] $begin_time [开始时间]
 * @param  [type] $end_time   [结束时间]
 * @return [type]             [description]
 */
function timediff($begin_time,$end_time)
{
    if($begin_time < $end_time){
        $starttime = $begin_time;
        $endtime = $end_time;
    }else{
        $starttime = $end_time;
        $endtime = $begin_time;
    }
    //计算天数
    $timediff = $endtime-$starttime;
    // 秒
    if (0 < $timediff and $timediff < 60) {
        return intval($timediff%60)."秒前";
    }
    // 分
    if (60 < $timediff and $timediff < 3600) {
        $remain = $timediff%3600;
        return intval($remain/60)."分前";
    }
    // 时
    if (3600 < $timediff and $timediff < 3600*24) {
        $remain = $timediff%86400;
        return intval($remain/3600)."小时前";
    }
    // 天
    if (3600*24 < $timediff) {
        return intval($timediff/86400)."天前";
    }
}

/**
 * 替换手机号码中间四位数字
 * @param  [type] $str [description]
 * @return [type]      [description]
 */
function hide_phone($str){
    $resstr = substr_replace($str,'****',3,4);  
    return $resstr;  
}