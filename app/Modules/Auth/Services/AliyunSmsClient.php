<?php

namespace App\Modules\Auth\Services;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use Darabonba\OpenApi\Models\Config;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * 阿里云短信服务客户端
 * 
 * 封装阿里云Dysmsapi SDK，提供短信发送功能
 * 使用DYPNS号码认证服务的短信验证功能
 * 
 * @version 1.0.0
 */
class AliyunSmsClient
{
    private Dysmsapi $client;
    private string $signName;
    private string $templateCode;

    public function __construct()
    {
        $this->initClient();
        $this->signName = config('aliyun.sms.sign_name');
        $this->templateCode = config('aliyun.sms.template_code');
    }

    /**
     * 初始化阿里云SDK客户端
     */
    private function initClient(): void
    {
        $accessKeyId = config('aliyun.access_key_id');
        $accessKeySecret = config('aliyun.access_key_secret');

        if (empty($accessKeyId) || empty($accessKeySecret)) {
            throw new Exception('阿里云AccessKey未配置，请检查.env文件');
        }

        $config = new Config([
            'accessKeyId' => $accessKeyId,
            'accessKeySecret' => $accessKeySecret,
        ]);
        
        // 设置Endpoint
        $config->endpoint = 'dysmsapi.aliyuncs.com';
        
        $this->client = new Dysmsapi($config);
    }

    /**
     * 发送短信验证码
     * 
     * @param string $phone 手机号
     * @param string $code 验证码
     * @param int $minutes 有效分钟数
     * @return array ['success' => bool, 'request_id' => string, 'message' => string, 'biz_id' => string]
     */
    public function sendSms(string $phone, string $code, int $minutes = 5): array
    {
        try {
            // 构建模板参数 - 根据模板100001的格式
            // 模板内容: 您的验证码为${code}。尊敬的客户，以上验证码${min}分钟内有效，请注意保密，切勿告知他人。
            $templateParam = json_encode([
                'code' => $code,
                'min' => (string) $minutes,
            ]);

            $request = new SendSmsRequest([
                'phoneNumbers' => $phone,
                'signName' => $this->signName,
                'templateCode' => $this->templateCode,
                'templateParam' => $templateParam,
            ]);

            $response = $this->client->sendSms($request);
            $body = $response->body;

            // 记录日志（手机号脱敏）
            $maskedPhone = $this->maskPhone($phone);
            Log::channel('daily')->info('短信发送请求', [
                'phone' => $maskedPhone,
                'request_id' => $body->requestId ?? '',
                'code' => $body->code ?? '',
                'message' => $body->message ?? '',
            ]);

            if ($body->code === 'OK') {
                return [
                    'success' => true,
                    'request_id' => $body->requestId,
                    'biz_id' => $body->bizId ?? '',
                    'message' => '短信发送成功',
                ];
            }

            // 处理常见错误码
            $errorMessage = $this->parseErrorCode($body->code, $body->message);
            
            return [
                'success' => false,
                'request_id' => $body->requestId ?? '',
                'biz_id' => '',
                'message' => $errorMessage,
            ];

        } catch (Exception $e) {
            Log::channel('daily')->error('短信发送异常', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'request_id' => '',
                'biz_id' => '',
                'message' => '短信发送失败：' . $e->getMessage(),
            ];
        }
    }

    /**
     * 解析阿里云错误码
     */
    private function parseErrorCode(string $code, string $message): string
    {
        $errorMap = [
            'isv.BUSINESS_LIMIT_CONTROL' => '短信发送频率超限，请稍后再试',
            'isv.MOBILE_NUMBER_ILLEGAL' => '手机号格式不正确',
            'isv.TEMPLATE_MISSING_PARAMETERS' => '短信模板参数缺失',
            'isv.INVALID_PARAMETERS' => '参数无效',
            'isv.AMOUNT_NOT_ENOUGH' => '短信余额不足',
            'isv.OUT_OF_SERVICE' => '短信服务暂停',
            'isv.PRODUCT_UN_SUBSCRIPT' => '短信服务未开通',
            'isv.PRODUCT_UNSUBSCRIBE' => '短信服务已退订',
            'isv.ACCOUNT_NOT_EXISTS' => '账户不存在',
            'isv.ACCOUNT_ABNORMAL' => '账户异常',
            'isv.SMS_TEMPLATE_ILLEGAL' => '短信模板不合法',
            'isv.SMS_SIGNATURE_ILLEGAL' => '短信签名不合法',
            'isv.INVALID_JSON_PARAM' => 'JSON参数格式错误',
            'isv.BLACK_KEY_CONTROL_LIMIT' => '手机号在黑名单中',
            'isv.PARAM_LENGTH_LIMIT' => '参数长度超限',
            'isv.PARAM_NOT_SUPPORT_URL' => '参数不支持URL',
            'isv.DAY_LIMIT_CONTROL' => '触发日发送限额',
            'isv.SMS_CONTENT_ILLEGAL' => '短信内容包含违禁词',
            'isv.SIGN_COUNT_OVER_LIMIT' => '签名数量超限',
            'isv.TEMPLATE_COUNT_OVER_LIMIT' => '模板数量超限',
            'isv.SIGN_NAME_ILLEGAL' => '签名名称不合法',
            'isv.SIGN_FILE_LIMIT' => '签名文件超限',
            'isv.SIGN_OVER_LIMIT' => '签名超限',
            'isv.TEMPLATE_OVER_LIMIT' => '模板超限',
        ];

        return $errorMap[$code] ?? "短信发送失败：{$message}";
    }

    /**
     * 手机号脱敏
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) >= 11) {
            return substr($phone, 0, 3) . '****' . substr($phone, -4);
        }
        return '***';
    }

    /**
     * 使用指定模板发送短信
     * 
     * @param string $phone 手机号
     * @param string $templateCode 模板代码
     * @param array $params 模板参数
     * @return array
     */
    public function sendSmsWithTemplate(string $phone, string $templateCode, array $params): array
    {
        try {
            $request = new SendSmsRequest([
                'phoneNumbers' => $phone,
                'signName' => $this->signName,
                'templateCode' => $templateCode,
                'templateParam' => json_encode($params),
            ]);

            $response = $this->client->sendSms($request);
            $body = $response->body;

            if ($body->code === 'OK') {
                return [
                    'success' => true,
                    'request_id' => $body->requestId,
                    'biz_id' => $body->bizId ?? '',
                    'message' => '短信发送成功',
                ];
            }

            return [
                'success' => false,
                'request_id' => $body->requestId ?? '',
                'biz_id' => '',
                'message' => $this->parseErrorCode($body->code, $body->message),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'request_id' => '',
                'biz_id' => '',
                'message' => '短信发送失败：' . $e->getMessage(),
            ];
        }
    }
}
