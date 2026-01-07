<?php

namespace App\Modules\Auth\Services;

use AlibabaCloud\SDK\Dypnsapi\V20170525\Dypnsapi;
use AlibabaCloud\SDK\Dypnsapi\V20170525\Models\SendSmsVerifyCodeRequest;
use AlibabaCloud\SDK\Dypnsapi\V20170525\Models\CheckSmsVerifyCodeRequest;
use Darabonba\OpenApi\Models\Config;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * 阿里云号码认证服务（DYPNS）客户端
 * 
 * 封装阿里云DYPNS SDK，提供短信验证码发送和验证功能
 * DYPNS是免资质、免签名、免模板的短信验证服务，适合个人开发者
 * 
 * @version 2.0.0
 */
class AliyunDypnsClient
{
    private Dypnsapi $client;

    public function __construct()
    {
        $this->initClient();
    }

    /**
     * 初始化阿里云DYPNS SDK客户端
     */
    private function initClient(): void
    {
        $accessKeyId = config('aliyun.access_key_id');
        $accessKeySecret = config('aliyun.access_key_secret');
        $region = config('aliyun.sms.region', 'cn-hangzhou');

        if (empty($accessKeyId) || empty($accessKeySecret)) {
            throw new Exception('阿里云AccessKey未配置，请检查.env文件');
        }

        $config = new Config([
            'accessKeyId' => $accessKeyId,
            'accessKeySecret' => $accessKeySecret,
            'regionId' => $region,
        ]);
        
        // DYPNS服务的Endpoint
        $config->endpoint = 'dypnsapi.aliyuncs.com';
        
        $this->client = new Dypnsapi($config);
    }

    /**
     * 发送短信验证码（使用DYPNS API）
     * 
     * DYPNS会自动生成6位验证码并发送，无需手动指定验证码
     * 需要使用签名和模板，但这些是系统预置的，不需要申请审核
     * 
     * @param string $phone 手机号
     * @return array ['success' => bool, 'request_id' => string, 'message' => string, 'code' => string]
     */
    public function sendSmsVerifyCode(string $phone): array
    {
        try {
            // 获取配置的签名和模板
            $signName = config('aliyun.sms.sign_name');
            $templateCode = config('aliyun.sms.template_code');
            
            if (empty($signName) || empty($templateCode)) {
                throw new Exception('短信签名或模板未配置，请在阿里云控制台查看预置资源');
            }
            
            // 创建请求对象并设置参数
            $request = new SendSmsVerifyCodeRequest();
            $request->phoneNumber = $phone;
            $request->signName = $signName;
            $request->templateCode = $templateCode;
            
            // 生成6位验证码并设置为模板参数
            // 根据阿里云DYPNS模板要求，需要传递code和min两个参数
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $codeExpireMinutes = (int)(config('aliyun.sms.code_expire', 300) / 60); // 转换为分钟
            $request->templateParam = json_encode([
                'code' => $code,
                'min' => (string)$codeExpireMinutes
            ]);

            $response = $this->client->sendSmsVerifyCode($request);
            $body = $response->body;

            // 记录日志（手机号脱敏）
            $maskedPhone = $this->maskPhone($phone);
            Log::channel('daily')->info('DYPNS短信发送请求', [
                'phone' => $maskedPhone,
                'request_id' => $body->requestId ?? '',
                'code' => $body->code ?? '',
                'message' => $body->message ?? '',
                'biz_id' => $body->bizId ?? '',
            ]);

            if ($body->code === 'OK') {
                return [
                    'success' => true,
                    'request_id' => $body->requestId,
                    'biz_id' => $body->bizId ?? '',
                    'message' => '短信发送成功',
                    // DYPNS不返回验证码，验证码由阿里云系统生成并发送
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
            Log::channel('daily')->error('DYPNS短信发送异常', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
     * 验证短信验证码（使用DYPNS API）
     * 
     * @param string $phone 手机号
     * @param string $code 验证码
     * @return array ['success' => bool, 'request_id' => string, 'message' => string]
     */
    public function checkSmsVerifyCode(string $phone, string $code): array
    {
        try {
            $request = new CheckSmsVerifyCodeRequest([
                'phoneNumber' => $phone,
                'verifyCode' => $code,
            ]);

            $response = $this->client->checkSmsVerifyCode($request);
            $body = $response->body;

            // 记录日志（手机号脱敏）
            $maskedPhone = $this->maskPhone($phone);
            Log::channel('daily')->info('DYPNS验证码验证请求', [
                'phone' => $maskedPhone,
                'request_id' => $body->requestId ?? '',
                'code' => $body->code ?? '',
                'message' => $body->message ?? '',
            ]);

            if ($body->code === 'OK') {
                return [
                    'success' => true,
                    'request_id' => $body->requestId,
                    'message' => '验证码验证成功',
                ];
            }

            // 处理验证失败的情况
            $errorMessage = $this->parseErrorCode($body->code, $body->message);
            
            return [
                'success' => false,
                'request_id' => $body->requestId ?? '',
                'message' => $errorMessage,
            ];

        } catch (Exception $e) {
            Log::channel('daily')->error('DYPNS验证码验证异常', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'request_id' => '',
                'message' => '验证码验证失败：' . $e->getMessage(),
            ];
        }
    }

    /**
     * 解析阿里云DYPNS错误码
     */
    private function parseErrorCode(string $code, string $message): string
    {
        $errorMap = [
            // DYPNS特有错误码
            'isv.MOBILE_NUMBER_ILLEGAL' => '手机号格式不正确',
            'isv.BUSINESS_LIMIT_CONTROL' => '短信发送频率超限，请稍后再试',
            'isv.INVALID_PARAMETERS' => '参数无效',
            'isv.AMOUNT_NOT_ENOUGH' => '短信余额不足',
            'isv.OUT_OF_SERVICE' => '短信服务暂停',
            'isv.PRODUCT_UN_SUBSCRIPT' => '短信服务未开通',
            'isv.PRODUCT_UNSUBSCRIBE' => '短信服务已退订',
            'isv.ACCOUNT_NOT_EXISTS' => '账户不存在',
            'isv.ACCOUNT_ABNORMAL' => '账户异常',
            'isv.BLACK_KEY_CONTROL_LIMIT' => '手机号在黑名单中',
            'isv.DAY_LIMIT_CONTROL' => '触发日发送限额',
            'isv.SMS_CONTENT_ILLEGAL' => '短信内容包含违禁词',
            
            // 验证码相关错误
            'isv.VERIFY_CODE_EXPIRED' => '验证码已过期',
            'isv.VERIFY_CODE_ERROR' => '验证码错误',
            'isv.VERIFY_CODE_NOT_EXIST' => '验证码不存在',
        ];

        return $errorMap[$code] ?? "操作失败：{$message}（错误码：{$code}）";
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
}
