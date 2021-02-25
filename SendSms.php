<?php
namespace app\api\controller;

use think\Controller;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

class SendSms extends Controller {

   // 优先加载
   public function  _initialize() {

   }

    static function  send_sms($accessKeyId,$accessSecret,$signName,$mobile,$code,$template){
        // $accessKeyId = db('smsconfig')->where("sms",'sms')->value('appkey');
        // $accessSecret = db('smsconfig')->where("sms",'sms')->value('secretkey');
        // $signName = db('smsconfig')->where("sms",'sms')->value('name');
        AlibabaCloud::accessKeyClient($accessKeyId, $accessSecret)
            ->regionId('cn-hangzhou')
            ->asDefaultClient();

        try {
            $result = AlibabaCloud::rpc()
                ->product('Dysmsapi')
                // ->scheme('https') // https | http
                ->version('2017-05-25')
                ->action('SendSms')
                ->method('POST')
                ->host('dysmsapi.aliyuncs.com')
                ->options([
                    'query' => [
                        'RegionId' => "cn-hangzhou",
                        'PhoneNumbers' => $mobile,
                        'SignName' => $signName,
                        'TemplateCode' => $template,
                        'TemplateParam' => $code,
                    ],
                ])
                ->request();
            return ($result->toArray());
        } catch (ClientException $e) {
            echo $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            echo $e->getErrorMessage() . PHP_EOL;
        }
    }
}