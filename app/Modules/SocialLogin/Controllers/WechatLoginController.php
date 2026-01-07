<?php

namespace App\Modules\SocialLogin\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\SocialLogin\Services\SocialLoginService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Overtrue\Socialite\SocialiteManager;

/**
 * Wechat Login Controller
 * 
 * 微信登录控制器
 */
class WechatLoginController extends BaseController
{
    protected SocialLoginService $socialLoginService;
    protected SocialiteManager $socialite;
    
    public function __construct(
        SocialLoginService $socialLoginService,
        SocialiteManager $socialite
    ) {
        $this->socialLoginService = $socialLoginService;
        $this->socialite = $socialite;
    }

    /**
     * 发起微信登录（重定向到微信授权页面）
     * 
     * @return JsonResponse
     */
    public function redirect(): JsonResponse
    {
        try {
            // 生成state参数用于安全验证
            $state = \Illuminate\Support\Str::random(40);
            \Illuminate\Support\Facades\Cache::put("wechat_oauth_state_{$state}", true, 600);
            
            // 获取微信授权URL
            $driver = $this->socialite->driver('wechat');
            $authUrl = $driver->stateless()->with(['state' => $state])->redirect()->getTargetUrl();
            
            return $this->success([
                'authorization_url' => $authUrl,
                'state' => $state,
            ], '请前往授权页面');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取微信授权URL');
        }
    }

    /**
     * 微信登录回调处理
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request)
    {
        try {
            // 验证state参数
            $state = $request->input('state');
            if (!$state || !\Illuminate\Support\Facades\Cache::has("wechat_oauth_state_{$state}")) {
                return redirect(config('app.frontend_url') . '/#/login?error=invalid_state');
            }
            
            // 清除state
            \Illuminate\Support\Facades\Cache::forget("wechat_oauth_state_{$state}");
            
            // 获取微信用户信息
            $wechatUser = $this->socialite->driver('wechat')->stateless()->user();
            
            // 格式化用户信息
            $socialUser = [
                'id' => $wechatUser->getId(),
                'nickname' => $wechatUser->getNickname(),
                'avatar' => $wechatUser->getAvatar(),
                'email' => $wechatUser->getEmail(),
                'access_token' => $wechatUser->getAccessToken(),
                'refresh_token' => $wechatUser->getRefreshToken(),
                'expires_in' => $wechatUser->getExpiresIn(),
                'raw' => $wechatUser->getRaw(),
            ];
            
            // 处理登录
            $result = $this->socialLoginService->handleCallback('wechat', $socialUser);
            
            // 重定向回前端（带token）
            $frontendUrl = config('app.frontend_url', 'http://localhost:9000');
            $callbackUrl = $frontendUrl . '/#/login/callback?token=' . $result['access_token'];
            
            return redirect($callbackUrl);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('微信登录失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $frontendUrl = config('app.frontend_url', 'http://localhost:9000');
            return redirect($frontendUrl . '/#/login?error=wechat_login_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * 绑定微信账号（需要已登录）
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bind(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // 获取授权码
            $code = $request->input('code');
            if (!$code) {
                return $this->fail('缺少授权码', 400);
            }
            
            // 通过授权码获取用户信息
            $wechatUser = $this->socialite->driver('wechat')->stateless()->userFromCode($code);
            
            $socialUser = [
                'id' => $wechatUser->getId(),
                'nickname' => $wechatUser->getNickname(),
                'avatar' => $wechatUser->getAvatar(),
                'email' => $wechatUser->getEmail(),
                'access_token' => $wechatUser->getAccessToken(),
                'refresh_token' => $wechatUser->getRefreshToken(),
                'expires_in' => $wechatUser->getExpiresIn(),
                'raw' => $wechatUser->getRaw(),
            ];
            
            // 绑定账号
            $result = $this->socialLoginService->bindAccount($user, 'wechat', $socialUser);
            
            return $this->success([
                'provider' => 'wechat',
                'provider_nickname' => $result['provider_nickname'],
                'provider_avatar' => $result['provider_avatar'],
                'bound_at' => $result['created_at'],
            ], '绑定微信账号成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '绑定微信账号');
        }
    }

    /**
     * 解绑微信账号
     * 
     * @return JsonResponse
     */
    public function unbind(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $result = $this->socialLoginService->unbindAccount($user, 'wechat');
            
            if (!$result) {
                return $this->fail('解绑失败');
            }
            
            return $this->success(null, '解绑微信账号成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '解绑微信账号');
        }
    }
}

