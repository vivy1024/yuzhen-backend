# 短信验证码API文档

**状态**: ✅ 已完成
**版本**: v2.0.0
**更新日期**: 2026-01-02

---

## 📋 概述

玉珍健身后端已完整实现阿里云DYPNS（号码认证服务）短信验证码功能，支持短信验证码发送、验证和登录。DYPNS是阿里云提供的免资质、免签名、免模板的短信验证服务，特别适合个人开发者。

### 核心特性

- ✅ **短信验证码发送** - 6位数字验证码，5分钟有效期
- ✅ **阿里云DYPNS** - 免资质、免签名、免模板审核
- ✅ **频率限制** - 60秒发送间隔，每日10次上限
- ✅ **IP限制** - 每分钟20次发送限制
- ✅ **验证失败锁定** - 5次失败后锁定15分钟
- ✅ **手机号验证码登录** - 验证码登录并返回JWT Token
- ✅ **Redis缓存** - 频率限制数据存储在Redis
- ✅ **完整日志** - 手机号脱敏日志记录

---

## 🔧 技术实现

### 1. 阿里云DYPNS配置 (`.env`)

```env
# 阿里云DYPNS短信服务配置
ALIYUN_ACCESS_KEY_ID=LTAI5t63ZTxgfs16eXSLoYHN
ALIYUN_ACCESS_KEY_SECRET=jYjQrJ4fWEqk67MHSyXiYsylRhSErs
ALIYUN_SMS_SIGN_NAME=云渚科技验证平台
ALIYUN_SMS_TEMPLATE_CODE=100001
ALIYUN_SMS_TEMPLATE_CODE_REGISTER=100001
ALIYUN_SMS_TEMPLATE_CODE_LOGIN=100001
ALIYUN_SMS_TEMPLATE_CODE_RESET=100003
ALIYUN_SMS_REGION=cn-hangzhou
ALIYUN_SMS_CODE_EXPIRE=300
```

**配置说明**:
- **ALIYUN_ACCESS_KEY_ID**: 阿里云AccessKey ID
- **ALIYUN_ACCESS_KEY_SECRET**: 阿里云AccessKey Secret
- **ALIYUN_SMS_SIGN_NAME**: 短信签名（系统预置）
- **ALIYUN_SMS_TEMPLATE_CODE**: 短信模板代码（系统预置）
- **ALIYUN_SMS_REGION**: 服务区域（cn-hangzhou）
- **ALIYUN_SMS_CODE_EXPIRE**: 验证码有效期（300秒 = 5分钟）

### 2. DYPNS客户端 (`AliyunDypnsClient.php`)

**核心功能**:
```php
class AliyunDypnsClient
{
    private Dypnsapi $client;
    
    // 发送短信验证码（DYPNS自动生成验证码）
    public function sendSmsVerifyCode(string $phone): array
    
    // 验证短信验证码（调用DYPNS API验证）
    public function checkSmsVerifyCode(string $phone, string $code): array
}
```

**DYPNS特点**:
- 验证码由阿里云系统自动生成和发送
- 验证码存储在阿里云系统，不需要本地存储
- 验证通过阿里云API进行，安全可靠
- 免资质、免签名、免模板审核

**SDK依赖**:
```json
{
  "require": {
    "alibabacloud/dypnsapi-20170525": "^2.0"
  }
}
```

### 3. 短信服务 (`SmsService.php`)

**核心功能**:
```php
class SmsService
{
    // 配置项
    private int $codeExpire = 300;        // 验证码有效期（5分钟）
    private int $codeLength = 6;          // 验证码长度（6位）
    private int $sendInterval = 60;       // 发送间隔（60秒）
    private int $dailyLimit = 10;         // 每日发送上限（10次）
    private int $ipMinuteLimit = 20;      // IP每分钟限制（20次）
    private int $verifyFailLimit = 5;     // 验证失败限制（5次）
    private int $lockDuration = 900;      // 锁定时长（15分钟）
}
```

**核心方法**:
- `sendVerificationCode($phone, $ip)` - 发送验证码
- `verifyCode($phone, $code)` - 验证验证码
- `loginWithSms($phone, $code, $ip)` - 手机号验证码登录
- `checkRateLimit($phone, $ip)` - 检查频率限制
- `isPhoneRegistered($phone)` - 检查手机号是否注册
- `maskPhone($phone)` - 手机号脱敏（日志记录）

**Redis Key设计**:
```php
private array $redisKeys = [
    'code' => 'sms:code:',              // 验证码存储（DYPNS不使用）
    'send_time' => 'sms:send_time:',    // 发送时间记录
    'daily_count' => 'sms:daily_count:', // 每日发送计数
    'ip_count' => 'sms:ip_count:',      // IP每分钟计数
    'fail_count' => 'sms:fail_count:',  // 验证失败计数
    'locked' => 'sms:locked:',          // 锁定标记
];
```

### 4. 配置文件 (`config/aliyun.php`)

```php
return [
    'access_key_id' => env('ALIYUN_ACCESS_KEY_ID', ''),
    'access_key_secret' => env('ALIYUN_ACCESS_KEY_SECRET', ''),
    
    'sms' => [
        'region' => env('ALIYUN_SMS_REGION', 'cn-hangzhou'),
        'sign_name' => env('ALIYUN_SMS_SIGN_NAME', ''),
        'template_code' => env('ALIYUN_SMS_TEMPLATE_CODE', ''),
        'code_expire' => (int) env('ALIYUN_SMS_CODE_EXPIRE', 300),
        'code_length' => 6,
    ],
    
    'rate_limit' => [
        'send_interval' => 60,
        'daily_limit' => 10,
        'ip_minute_limit' => 20,
        'verify_fail_limit' => 5,
        'lock_duration' => 900,
    ],
];
```

---

## 📡 API端点

### 1. 发送短信验证码

**端点**: `POST /api/auth/sms/send`

**请求参数**:
```json
{
  "phone": "13800138000"
}
```

**成功响应** (200):
```json
{
  "success": true,
  "message": "验证码已发送",
  "data": {
    "expires_at": 1704182400,
    "wait_seconds": 60
  }
}
```

**错误响应**:

| 状态码 | 错误码 | 说明 |
|--------|--------|------|
| 400 | SMS_INVALID_PHONE | 手机号格式不正确 |
| 429 | SMS_RATE_LIMITED | 发送过于频繁 |
| 429 | SMS_DAILY_LIMIT | 今日发送次数已达上限 |
| 500 | SMS_SEND_FAILED | 短信发送失败 |

**频率限制**:
- 同一手机号：60秒内只能发送1次
- 同一手机号：每天最多发送10次
- 同一IP：每分钟最多发送20次

### 2. 验证短信验证码

**端点**: `POST /api/auth/sms/verify`

**请求参数**:
```json
{
  "phone": "13800138000",
  "code": "123456"
}
```

**成功响应** (200):
```json
{
  "success": true,
  "message": "验证成功",
  "data": {
    "verified": true
  }
}
```

**错误响应**:

| 状态码 | 错误码 | 说明 |
|--------|--------|------|
| 400 | SMS_CODE_INVALID | 验证码错误 |
| 400 | SMS_CODE_EXPIRED | 验证码已过期 |
| 403 | SMS_PHONE_LOCKED | 验证失败次数过多，已锁定15分钟 |

**验证失败锁定**:
- 5次验证失败后锁定15分钟
- 锁定期间无法验证和发送验证码

### 3. 手机号验证码登录

**端点**: `POST /api/auth/sms/login`

**请求参数**:
```json
{
  "phone": "13800138000",
  "code": "123456"
}
```

**成功响应** (200):
```json
{
  "success": true,
  "message": "登录成功",
  "data": {
    "user": {
      "id": 1,
      "name": "张三",
      "email": "user@example.com",
      "phone": "13800138000",
      "avatar": "https://example.com/avatar.jpg"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

**错误响应**:

| 状态码 | 错误码 | 说明 |
|--------|--------|------|
| 404 | SMS_PHONE_NOT_FOUND | 该手机号未注册 |
| 400 | SMS_CODE_INVALID | 验证码错误 |
| 400 | SMS_CODE_EXPIRED | 验证码已过期 |
| 403 | SMS_PHONE_LOCKED | 验证失败次数过多 |
| 403 | USER_DISABLED | 用户已被禁用 |

### 4. 检查手机号是否注册

**端点**: `GET /api/auth/sms/check-phone?phone=13800138000`

**成功响应** (200):
```json
{
  "success": true,
  "data": {
    "phone": "13800138000",
    "is_registered": true
  }
}
```

---

## 🔄 工作流程

### 发送验证码流程

```
用户请求发送验证码
    ↓
验证手机号格式
    ↓
检查频率限制（60秒间隔、每日10次、IP每分钟20次）
    ↓
调用DYPNS API发送短信
    ↓
DYPNS自动生成6位验证码并发送
    ↓
记录发送时间和计数
    ↓
返回成功响应
```

### 验证验证码流程

```
用户提交验证码
    ↓
检查是否被锁定
    ↓
调用DYPNS API验证验证码
    ↓
验证成功：清除失败计数
验证失败：增加失败计数，5次后锁定15分钟
    ↓
返回验证结果
```

### 手机号验证码登录流程

```
用户提交手机号和验证码
    ↓
调用verifyCode验证验证码
    ↓
验证成功后查找用户
    ↓
检查用户状态（是否禁用）
    ↓
更新最后登录时间
    ↓
生成JWT Token（access_token + refresh_token）
    ↓
返回用户信息和Token
```

---

## 🔐 安全机制

### 1. 频率限制

**发送频率限制**:
- 同一手机号60秒内只能发送1次
- 同一手机号每天最多发送10次
- 同一IP每分钟最多发送20次

**验证频率限制**:
- 5次验证失败后锁定15分钟
- 锁定期间无法验证和发送验证码

### 2. 验证码安全

- 6位数字验证码（100万种组合）
- 5分钟有效期（自动过期）
- 验证由阿里云DYPNS系统处理（安全可靠）
- Redis存储频率限制数据

### 3. 日志记录

**手机号脱敏**:
```php
// 原始手机号: 13800138000
// 脱敏后: 138****8000
private function maskPhone(string $phone): string
{
    if (strlen($phone) >= 11) {
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }
    return '***';
}
```

**日志内容**:
- 发送成功/失败日志
- 验证成功/失败日志
- 锁定警告日志
- 登录成功日志
- DYPNS API请求日志

### 4. 错误处理

- 短信发送失败自动捕获异常
- 详细的错误码和错误信息
- 不暴露敏感信息（手机号脱敏）
- DYPNS错误码映射为友好提示

---

## 📊 Redis数据结构

### 发送时间记录

```
Key: sms:send_time:13800138000
Value: 1704182400
TTL: 60秒
```

### 每日发送计数

```
Key: sms:daily_count:13800138000:2026-01-02
Value: 3
TTL: 86400秒（24小时）
```

### IP每分钟计数

```
Key: sms:ip_count:192.168.1.1:2026-01-02-14-30
Value: 2
TTL: 60秒
```

### 验证失败计数

```
Key: sms:fail_count:13800138000
Value: 2
TTL: 900秒（15分钟）
```

### 锁定标记

```
Key: sms:locked:13800138000
Value: 1
TTL: 900秒（15分钟）
```

---

## 🎯 DYPNS特点

### 1. 免资质服务

- **无需企业资质** - 个人开发者可直接使用
- **无需申请签名** - 系统自动赠送预置签名
- **无需申请模板** - 系统自动赠送预置模板
- **快速开通** - 开通即用，无需等待审核

### 2. 系统预置资源

**预置签名**:
- 签名名称：云渚科技验证平台
- 签名来源：系统预置
- 无需审核

**预置模板**:
- 模板代码：100001（通用验证码）
- 模板代码：100003（重置密码）
- 模板内容：您的验证码是${code}，${min}分钟内有效
- 无需审核

### 3. 计费方式

- 按量计费，0.045元/条
- 新用户赠送100条免费额度
- 支持预付费套餐包

### 4. 技术优势

- 验证码由阿里云生成（安全可靠）
- 验证码存储在阿里云（无需本地存储）
- 验证通过API进行（防止伪造）
- 支持全国三网（移动、联通、电信）

---

## 🎯 前端集成示例

### 发送验证码

```typescript
import api from '@/api/auth'

async function sendSmsCode(phone: string) {
  try {
    const response = await api.post('/auth/sms/send', { phone })
    
    if (response.success) {
      showSuccess('验证码已发送')
      // 开始倒计时
      startCountdown(60)
    }
  } catch (error: any) {
    if (error.response?.data?.code === 'SMS_RATE_LIMITED') {
      const waitSeconds = error.response.data.data.wait_seconds
      showError(`发送过于频繁，请${waitSeconds}秒后重试`)
    } else {
      showError(error.message || '发送失败')
    }
  }
}
```

### 手机号验证码登录

```typescript
async function smsLogin(phone: string, code: string) {
  try {
    const response = await api.post('/auth/sms/login', { phone, code })
    
    if (response.success) {
      // 保存Token
      localStorage.setItem('access_token', response.data.access_token)
      localStorage.setItem('refresh_token', response.data.refresh_token)
      localStorage.setItem('user_info', JSON.stringify(response.data.user))
      
      showSuccess('登录成功')
      router.push('/')
    }
  } catch (error: any) {
    if (error.response?.data?.code === 'SMS_PHONE_NOT_FOUND') {
      showError('该手机号未注册')
    } else if (error.response?.data?.code === 'SMS_CODE_INVALID') {
      showError('验证码错误')
    } else {
      showError(error.message || '登录失败')
    }
  }
}
```

---

## 🔧 DYPNS错误码映射

### 常见错误码

| 阿里云错误码 | 友好提示 |
|-------------|---------|
| isv.MOBILE_NUMBER_ILLEGAL | 手机号格式不正确 |
| isv.BUSINESS_LIMIT_CONTROL | 短信发送频率超限，请稍后再试 |
| isv.INVALID_PARAMETERS | 参数无效 |
| isv.AMOUNT_NOT_ENOUGH | 短信余额不足 |
| isv.OUT_OF_SERVICE | 短信服务暂停 |
| isv.PRODUCT_UN_SUBSCRIPT | 短信服务未开通 |
| isv.BLACK_KEY_CONTROL_LIMIT | 手机号在黑名单中 |
| isv.DAY_LIMIT_CONTROL | 触发日发送限额 |
| isv.VERIFY_CODE_EXPIRED | 验证码已过期 |
| isv.VERIFY_CODE_ERROR | 验证码错误 |
| isv.VERIFY_CODE_NOT_EXIST | 验证码不存在 |

---

## 🧪 测试验证

### 1. 发送验证码测试

```bash
# 使用Postman或curl测试
POST http://localhost:8000/api/auth/sms/send
Content-Type: application/json

{
  "phone": "13800138000"
}
```

**预期结果**:
- 返回200状态码
- 收到短信（检查手机）
- Redis中记录发送时间

### 2. 验证验证码测试

```bash
POST http://localhost:8000/api/auth/sms/verify
Content-Type: application/json

{
  "phone": "13800138000",
  "code": "123456"
}
```

### 3. 频率限制测试

**测试60秒发送间隔**:
- 连续发送2次验证码
- 第2次应返回429错误

**测试每日10次上限**:
- 同一手机号发送11次验证码
- 第11次应返回429错误

**测试IP每分钟20次限制**:
- 同一IP发送21次验证码（不同手机号）
- 第21次应返回429错误

### 4. 验证失败锁定测试

- 连续输入5次错误验证码
- 第5次应返回403错误（锁定15分钟）

---

## 🎉 后续优化方向

### 1. 短信模板扩展
- [ ] 注册验证码模板
- [ ] 登录验证码模板
- [ ] 密码重置模板
- [ ] 绑定手机号模板

### 2. 发送渠道优化
- [ ] 支持多个短信服务商（故障转移）
- [ ] 集成其他短信服务（腾讯云、华为云）
- [ ] 短信发送队列（异步发送）

### 3. 安全增强
- [ ] 图形验证码（防止机器人）
- [ ] 设备指纹识别
- [ ] 异常登录检测
- [ ] 短信轰炸防护

### 4. 监控和统计
- [ ] 短信发送成功率统计
- [ ] 验证码使用率分析
- [ ] 异常行为监控和告警
- [ ] 成本分析和优化

---

## 📝 相关文档

- **认证系统API**: `yuzhen-backend/docs/05-API文档/01-认证系统API.md`
- **邮件验证码API**: `yuzhen-backend/docs/05-API文档/04-邮件验证码API.md`
- **阿里云DYPNS文档**: https://help.aliyun.com/zh/pnvs/getting-started/sms-authentication-service-novice-guide

---

**维护者**: 薛小川
**最后更新**: 2026-01-02
