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
     * 免资质通用模板需要传入验证码，阿里云只负责发送不负责存储
     * 验证码需本地存储（Redis），验证时查本地
     *
     * @param string $phone 手机号
     * @param string $code 验证码（6位数字）
     * @return array ['success' => bool, 'request_id' => string, 'message' => string, 'biz_id' => string]
     */
    public function sendSmsVerifyCode(string $phone, string $code = ''): array
    {
        try {
            $signName = config('aliyun.sms.sign_name');
            $templateCode = config('aliyun.sms.template_code');

            if (empty($signName) || empty($templateCode)) {
                throw new Exception('短信签名或模板未配置，请在阿里云控制台查看预置资源');
            }

            // 验证码格式校验
            if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
                throw new Exception('验证码格式错误，必须为6位数字');
            }

            $codeExpireMinutes = (int)(config('aliyun.sms.code_expire', 300) / 60);

            $request = new SendSmsVerifyCodeRequest([
                'phoneNumber' => $phone,
                'signName' => $signName,
                'templateCode' => $templateCode,
                'codeLength' => 6,
                'validTime' => $codeExpireMinutes,
                'interval' => config('aliyun.rate_limit.send_interval', 60),
                // 免资质通用模板需传递模板参数（code/min 对应模板占位符）
                'templateParam' => json_encode([
                    'code' => $code,
                    'min' => (string)$codeExpireMinutes,
                ]),
            ]);

            $response = $this->client->sendSmsVerifyCode($request);
            $body = $response->body;

            $maskedPhone = $this->maskPhone($phone);
            $model = $body->model;

            Log::channel('daily')->info('DYPNS短信发送请求', [
                'phone' => $maskedPhone,
                'code' => $body->code ?? '',
                'message' => $body->message ?? '',
                'request_id' => $model->requestId ?? '',
                'biz_id' => $model->bizId ?? '',
            ]);

            if ($body->code === 'OK') {
                return [
                    'success' => true,
                    'request_id' => $model->requestId ?? '',
                    'biz_id' => $model->bizId ?? '',
                    'message' => '短信发送成功',
                ];
            }

            $errorMessage = $this->parseErrorCode($body->code, $body->message);

            return [
                'success' => false,
                'request_id' => $model->requestId ?? '',
                'biz_id' => '',
                'message' => $errorMessage,
            ];

        } catch (Exception $e) {
            Log::channel('daily')->error('DYPNS短信发送异常', [
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
            $model = $body->model;

            $maskedPhone = $this->maskPhone($phone);
            Log::channel('daily')->info('DYPNS验证码验证请求', [
                'phone' => $maskedPhone,
                'code' => $body->code ?? '',
                'message' => $body->message ?? '',
                'verify_result' => $model->verifyResult ?? '',
            ]);

            if ($body->code === 'OK' && ($model->verifyResult ?? '') === 'PASS') {
                return [
                    'success' => true,
                    'request_id' => $body->requestId ?? '',
                    'message' => '验证码验证成功',
                ];
            }

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
            // DYPNS 频率/业务错误码
            'biz.FREQUENCY' => '短信发送过于频繁，请稍后再试',
            'biz.VERIFY_CODE_EXPIRED' => '验证码已过期',
            'biz.VERIFY_CODE_ERROR' => '验证码错误',
            'biz.VERIFY_CODE_NOT_EXIST' => '验证码不存在，请重新发送',

            // 阿里云通用错误码
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
