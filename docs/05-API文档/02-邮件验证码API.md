# 邮件验证码API文档

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-02

---

## 📋 概述

玉珍健身后端已完整实现SMTP邮件验证码功能，支持邮箱验证码发送、验证和登录。使用Laravel内置Mail功能，配置Spacemail企业邮箱服务。

### 核心特性

- ✅ **邮件验证码发送** - 6位数字验证码，5分钟有效期
- ✅ **频率限制** - 60秒发送间隔，每日10次上限
- ✅ **IP限制** - 每分钟5次发送限制
- ✅ **验证失败锁定** - 5次失败后锁定15分钟
- ✅ **邮箱验证码登录** - 验证码登录并返回JWT Token
- ✅ **Redis缓存** - 验证码和限制数据存储在Redis
- ✅ **精美邮件模板** - 响应式HTML邮件模板

---

## 🔧 技术实现

### 1. SMTP配置 (`.env`)

```env
# 邮件配置 - Spacemail
MAIL_MAILER=smtp
MAIL_HOST=mail.spacemail.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@xxc1024.site
MAIL_PASSWORD=Xxxc3294553478.
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=no-reply@xxc1024.site
MAIL_FROM_NAME="玉珍健身"

# 邮箱验证码配置
MAIL_VERIFICATION_CODE_EXPIRE=300
```

**配置说明**:
- **MAIL_HOST**: Spacemail SMTP服务器
- **MAIL_PORT**: 465端口（SSL加密）
- **MAIL_ENCRYPTION**: SSL加密传输
- **MAIL_FROM_ADDRESS**: 发件人邮箱（no-reply@xxc1024.site）
- **MAIL_FROM_NAME**: 发件人名称（玉珍健身）
- **MAIL_VERIFICATION_CODE_EXPIRE**: 验证码有效期（300秒 = 5分钟）

### 2. 邮件服务 (`EmailService.php`)

**核心功能**:
```php
class EmailService
{
    // 配置项
    private int $codeExpire = 300;        // 验证码有效期（5分钟）
    private int $codeLength = 6;          // 验证码长度（6位）
    private int $sendInterval = 60;       // 发送间隔（60秒）
    private int $dailyLimit = 10;         // 每日发送上限（10次）
    private int $ipMinuteLimit = 5;       // IP每分钟限制（5次）
    private int $verifyFailLimit = 5;     // 验证失败限制（5次）
    private int $lockDuration = 900;      // 锁定时长（15分钟）
}
```

**核心方法**:
- `sendVerificationCode($email, $ip)` - 发送验证码
- `verifyCode($email, $code)` - 验证验证码
- `login($email, $code)` - 邮箱验证码登录
- `checkRateLimit($email, $ip)` - 检查频率限制
- `generateCode()` - 生成6位数字验证码
- `maskEmail($email)` - 邮箱脱敏（日志记录）

**Redis Key设计**:
```php
private array $redisKeys = [
    'code' => 'email:code:',              // 验证码存储
    'send_time' => 'email:send_time:',    // 发送时间记录
    'daily_count' => 'email:daily_count:', // 每日发送计数
    'ip_count' => 'email:ip_count:',      // IP每分钟计数
    'fail_count' => 'email:fail_count:',  // 验证失败计数
    'locked' => 'email:locked:',          // 锁定标记
];
```

### 3. 邮件模板 (`verification-code.blade.php`)

**模板特性**:
- 响应式设计（适配移动端和桌面端）
- 精美的视觉设计（蓝色主题）
- 清晰的验证码展示（32px大字体，8px字间距）
- 安全提示（警告框）
- 品牌标识（玉珍健身Logo）

**模板结构**:
```html
<div class="container">
    <div class="header">
        <div class="logo">🏋️ 玉珍健身</div>
        <div class="title">邮箱验证码</div>
    </div>
    
    <div class="code-box">
        <div class="code">{{ $code }}</div>
    </div>
    
    <div class="warning">
        ⚠️ 如果这不是您本人的操作，请忽略此邮件
    </div>
    
    <div class="footer">
        © 2026 玉珍健身 版权所有
    </div>
</div>
```

### 4. 邮件类 (`VerificationCodeMail.php`)

```php
class VerificationCodeMail extends Mailable
{
    public string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【玉珍健身】邮箱验证码',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            with: ['code' => $this->code],
        );
    }
}
```

---

## 📡 API端点

### 1. 发送邮箱验证码

**端点**: `POST /api/auth/email/send`

**请求参数**:
```json
{
  "email": "user@example.com"
}
```

**成功响应** (200):
```json
{
  "success": true,
  "message": "验证码已发送到您的邮箱",
  "data": {
    "expires_at": 1704182400
  }
}
```

**错误响应**:

| 状态码 | 错误码 | 说明 |
|--------|--------|------|
| 400 | EMAIL_INVALID_FORMAT | 邮箱格式不正确 |
| 429 | EMAIL_RATE_LIMITED | 发送过于频繁 |
| 429 | EMAIL_DAILY_LIMIT | 今日发送次数已达上限 |
| 403 | EMAIL_LOCKED | 该邮箱已被锁定 |
| 500 | EMAIL_SEND_FAILED | 邮件发送失败 |

**频率限制**:
- 同一邮箱：60秒内只能发送1次
- 同一邮箱：每天最多发送10次
- 同一IP：每分钟最多发送5次

### 2. 验证邮箱验证码

**端点**: `POST /api/auth/email/verify`

**请求参数**:
```json
{
  "email": "user@example.com",
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
| 400 | EMAIL_CODE_INVALID | 验证码错误 |
| 400 | EMAIL_CODE_EXPIRED | 验证码已过期或不存在 |
| 403 | EMAIL_LOCKED | 验证失败次数过多，已锁定15分钟 |

**验证失败锁定**:
- 5次验证失败后锁定15分钟
- 锁定期间无法验证和发送验证码

### 3. 邮箱验证码登录

**端点**: `POST /api/auth/email/login`

**请求参数**:
```json
{
  "email": "user@example.com",
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
    "expires_in": 3600
  }
}
```

**错误响应**:

| 状态码 | 错误码 | 说明 |
|--------|--------|------|
| 404 | EMAIL_NOT_FOUND | 该邮箱未注册 |
| 400 | EMAIL_CODE_INVALID | 验证码错误 |
| 400 | EMAIL_CODE_EXPIRED | 验证码已过期 |
| 403 | EMAIL_LOCKED | 验证失败次数过多 |

---

## 🔄 工作流程

### 发送验证码流程

```
用户请求发送验证码
    ↓
验证邮箱格式
    ↓
检查频率限制（60秒间隔、每日10次、IP每分钟5次）
    ↓
生成6位数字验证码
    ↓
发送邮件（Laravel Mail + Spacemail SMTP）
    ↓
存储验证码到Redis（5分钟有效期）
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
从Redis获取存储的验证码
    ↓
验证码比对
    ↓
验证成功：删除验证码和失败计数
验证失败：增加失败计数，5次后锁定15分钟
    ↓
返回验证结果
```

### 邮箱验证码登录流程

```
用户提交邮箱和验证码
    ↓
调用verifyCode验证验证码
    ↓
验证成功后查找用户
    ↓
生成JWT Token（access_token + refresh_token）
    ↓
返回用户信息和Token
```

---

## 🔐 安全机制

### 1. 频率限制

**发送频率限制**:
- 同一邮箱60秒内只能发送1次
- 同一邮箱每天最多发送10次
- 同一IP每分钟最多发送5次

**验证频率限制**:
- 5次验证失败后锁定15分钟
- 锁定期间无法验证和发送验证码

### 2. 验证码安全

- 6位数字验证码（100万种组合）
- 5分钟有效期（自动过期）
- 验证成功后立即删除（一次性使用）
- Redis存储（内存级性能）

### 3. 日志记录

**邮箱脱敏**:
```php
// 原始邮箱: user@example.com
// 脱敏后: u***r@example.com
private function maskEmail(string $email): string
{
    $parts = explode('@', $email);
    $username = $parts[0];
    $len = strlen($username);
    $masked = $username[0] . str_repeat('*', min($len - 2, 4)) . $username[$len - 1];
    return $masked . '@' . $parts[1];
}
```

**日志内容**:
- 发送成功/失败日志
- 验证成功/失败日志
- 锁定警告日志
- 登录成功日志

### 4. 错误处理

- 邮件发送失败自动捕获异常
- 详细的错误码和错误信息
- 不暴露敏感信息（邮箱脱敏）

---

## 📊 Redis数据结构

### 验证码存储

```
Key: email:code:user@example.com
Value: "123456"
TTL: 300秒（5分钟）
```

### 发送时间记录

```
Key: email:send_time:user@example.com
Value: 1704182400
TTL: 60秒
```

### 每日发送计数

```
Key: email:daily_count:user@example.com:2026-01-02
Value: 3
TTL: 86400秒（24小时）
```

### IP每分钟计数

```
Key: email:ip_count:192.168.1.1:202601021430
Value: 2
TTL: 60秒
```

### 验证失败计数

```
Key: email:fail_count:user@example.com
Value: 2
TTL: 900秒（15分钟）
```

### 锁定标记

```
Key: email:locked:user@example.com
Value: "1"
TTL: 900秒（15分钟）
```

---

## 🎯 前端集成示例

### 发送验证码

```typescript
import api from '@/api/auth'

async function sendEmailCode(email: string) {
  try {
    const response = await api.post('/auth/email/send', { email })
    
    if (response.success) {
      showSuccess('验证码已发送到您的邮箱')
      // 开始倒计时
      startCountdown(60)
    }
  } catch (error: any) {
    if (error.response?.data?.code === 'EMAIL_RATE_LIMITED') {
      const waitSeconds = error.response.data.data.wait_seconds
      showError(`发送过于频繁，请${waitSeconds}秒后重试`)
    } else {
      showError(error.message || '发送失败')
    }
  }
}
```

### 邮箱验证码登录

```typescript
async function emailLogin(email: string, code: string) {
  try {
    const response = await api.post('/auth/email/login', { email, code })
    
    if (response.success) {
      // 保存Token
      localStorage.setItem('access_token', response.data.access_token)
      localStorage.setItem('refresh_token', response.data.refresh_token)
      localStorage.setItem('user_info', JSON.stringify(response.data.user))
      
      showSuccess('登录成功')
      router.push('/')
    }
  } catch (error: any) {
    if (error.response?.data?.code === 'EMAIL_NOT_FOUND') {
      showError('该邮箱未注册')
    } else if (error.response?.data?.code === 'EMAIL_CODE_INVALID') {
      showError('验证码错误')
    } else {
      showError(error.message || '登录失败')
    }
  }
}
```

---

## 🔧 配置管理

### Laravel配置 (`config/mail.php`)

```php
return [
    'default' => env('MAIL_MAILER', 'smtp'),
    
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
        ],
    ],
    
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],
    
    'verification_code_expire' => env('MAIL_VERIFICATION_CODE_EXPIRE', 300),
];
```

### 环境变量说明

| 变量 | 说明 | 示例值 |
|------|------|--------|
| MAIL_MAILER | 邮件驱动 | smtp |
| MAIL_HOST | SMTP服务器 | mail.spacemail.com |
| MAIL_PORT | SMTP端口 | 465 |
| MAIL_USERNAME | SMTP用户名 | no-reply@xxc1024.site |
| MAIL_PASSWORD | SMTP密码 | Xxxc3294553478. |
| MAIL_ENCRYPTION | 加密方式 | ssl |
| MAIL_FROM_ADDRESS | 发件人邮箱 | no-reply@xxc1024.site |
| MAIL_FROM_NAME | 发件人名称 | 玉珍健身 |
| MAIL_VERIFICATION_CODE_EXPIRE | 验证码有效期（秒） | 300 |

---

## 🧪 测试验证

### 1. 发送验证码测试

```bash
# 使用Postman或curl测试
POST http://localhost:8000/api/auth/email/send
Content-Type: application/json

{
  "email": "test@example.com"
}
```

**预期结果**:
- 返回200状态码
- 收到邮件（检查邮箱收件箱）
- Redis中存储验证码

### 2. 验证验证码测试

```bash
POST http://localhost:8000/api/auth/email/verify
Content-Type: application/json

{
  "email": "test@example.com",
  "code": "123456"
}
```

### 3. 频率限制测试

**测试60秒发送间隔**:
- 连续发送2次验证码
- 第2次应返回429错误

**测试每日10次上限**:
- 同一邮箱发送11次验证码
- 第11次应返回429错误

**测试IP每分钟5次限制**:
- 同一IP发送6次验证码（不同邮箱）
- 第6次应返回429错误

### 4. 验证失败锁定测试

- 连续输入5次错误验证码
- 第5次应返回403错误（锁定15分钟）

---

## 🎉 后续优化方向

### 1. 邮件模板增强
- [ ] 支持多语言邮件模板
- [ ] 添加更多邮件类型（欢迎邮件、密码重置等）
- [ ] 邮件模板可视化编辑器

### 2. 发送渠道优化
- [ ] 支持多个SMTP服务商（故障转移）
- [ ] 集成第三方邮件服务（SendGrid、Mailgun）
- [ ] 邮件发送队列（异步发送）

### 3. 安全增强
- [ ] 图形验证码（防止机器人）
- [ ] 设备指纹识别
- [ ] 异常登录检测

### 4. 监控和统计
- [ ] 邮件发送成功率统计
- [ ] 验证码使用率分析
- [ ] 异常行为监控和告警

---

## 📝 相关文档

- **认证系统API**: `yuzhen-backend/docs/05-API文档/01-认证系统API.md`
- **短信验证码API**: `yuzhen-backend/docs/05-API文档/03-短信验证码API.md`
- **Laravel Mail文档**: https://laravel.com/docs/10.x/mail

---

**维护者**: 薛小川
**最后更新**: 2026-01-02
