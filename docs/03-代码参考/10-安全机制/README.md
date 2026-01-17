# 10-安全机制

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-17

本目录包含API安全、数据加密、GraphRAG安全、频率限制的代码实现细节。

---

## 📚 文档列表

### [03-GraphRAG安全](./03-GraphRAG安全.md)
- GraphRAG访问控制
- 查询权限验证
- 数据隔离机制
- 安全最佳实践

---

## 🔧 核心安全机制

### JWT认证
- **Token生成**: 用户登录后生成JWT Token
- **Token验证**: 中间件验证Token有效性
- **Token刷新**: 定期刷新Token延长有效期
- **Token黑名单**: Redis存储已注销Token

### API安全
- **HTTPS加密**: 所有API使用HTTPS
- **CORS配置**: 跨域请求控制
- **请求签名**: 关键API使用签名验证
- **频率限制**: 防止API滥用

### 数据加密
- **密码加密**: Bcrypt哈希算法
- **敏感数据**: AES加密存储
- **传输加密**: TLS/SSL协议

### GraphRAG安全
- **访问控制**: 基于用户权限的查询限制
- **数据隔离**: 用户数据隔离
- **查询审计**: 记录所有GraphRAG查询

---

## 📊 安全架构

### 认证流程

```
用户登录 → 验证凭证 → 生成JWT Token → 返回Token
    ↓
后续请求 → 携带Token → 中间件验证 → 解析用户信息 → 允许访问
```

### 权限验证

```php
// 中间件验证
class Authenticate extends Middleware
{
    public function handle($request, Closure $next)
    {
        if (!$request->user()) {
            return response()->json([
                'code' => 401,
                'msg' => '未认证'
            ], 401);
        }
        
        return $next($request);
    }
}
```

---

## 🔐 安全最佳实践

### 1. 密码安全

```php
// 密码加密
$hashedPassword = Hash::make($password);

// 密码验证
if (Hash::check($password, $user->password)) {
    // 密码正确
}
```

### 2. SQL注入防护

```php
// 使用参数绑定
$users = DB::select('SELECT * FROM users WHERE email = ?', [$email]);

// 使用查询构建器
$users = User::where('email', $email)->get();
```

### 3. XSS防护

```php
// 自动转义输出
{{ $user->name }}

// 手动转义
{!! e($user->bio) !!}
```

### 4. CSRF防护

```php
// 表单CSRF Token
<form method="POST">
    @csrf
    <!-- 表单字段 -->
</form>
```

---

## 🚦 频率限制

### API限流配置

```php
// 全局限流
Route::middleware('throttle:60,1')->group(function () {
    // 每分钟60次请求
});

// 特定路由限流
Route::middleware('throttle:10,1')->post('/api/auth/login');
```

### 自定义限流

```php
// 基于用户的限流
RateLimiter::for('api', function (Request $request) {
    return $request->user()
        ? Limit::perMinute(100)->by($request->user()->id)
        : Limit::perMinute(10)->by($request->ip());
});
```

---

## 📝 安全审计

### 日志记录

```php
// 记录安全事件
Log::warning('Failed login attempt', [
    'email' => $request->email,
    'ip' => $request->ip(),
    'timestamp' => now()
]);

// 记录敏感操作
Log::info('User data updated', [
    'user_id' => $user->id,
    'fields' => $request->only(['name', 'email']),
    'ip' => $request->ip()
]);
```

### 监控告警

- **异常登录**: 检测异常登录行为
- **API滥用**: 监控API调用频率
- **数据泄露**: 检测敏感数据访问

---

## 🔗 相关文档

- [认证系统](../01-认证系统/README.md)
- [API文档](../../05-API文档/README.md)
- [部署运维](../../06-部署运维/README.md)

---

**维护者**: 薛小川
**最后更新**: 2026-01-17
