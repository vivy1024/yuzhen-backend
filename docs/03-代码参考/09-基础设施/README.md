# 09-基础设施

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-17

本目录包含Redis缓存、队列系统、日志系统、监控系统的代码实现细节。

---

## 📚 文档列表

### [01-基础设施总览](./01-基础设施总览.md)
- 基础设施架构
- 组件集成方案
- 配置管理
- 最佳实践

### [05-仓储层实现](./05-仓储层实现.md)
- Repository模式
- 数据访问抽象
- 查询构建器
- 缓存策略

---

## 🔧 核心组件

### Redis缓存
- **用途**: 数据缓存、会话存储、队列
- **配置**: `config/database.php`
- **驱动**: PhpRedis扩展

### 队列系统
- **用途**: 异步任务处理
- **驱动**: Redis队列
- **任务**: 邮件发送、数据同步

### 日志系统
- **用途**: 应用日志、错误追踪
- **配置**: `config/logging.php`
- **存储**: 文件日志、数据库日志

### 监控系统
- **用途**: 性能监控、健康检查
- **指标**: 响应时间、错误率、资源使用

---

## 📊 基础设施架构

### Redis使用场景

1. **数据缓存**
   - 用户信息缓存
   - API响应缓存
   - 查询结果缓存

2. **会话存储**
   - 用户会话
   - JWT Token黑名单

3. **队列任务**
   - 邮件发送队列
   - 数据同步队列

### 缓存策略

```php
// 缓存用户信息
Cache::put('user_' . $userId, $user, 3600);

// 查询缓存
$users = Cache::remember('active_users', 3600, function () {
    return User::where('is_active', true)->get();
});

// 清除缓存
Cache::forget('user_' . $userId);
```

---

## 🔄 队列处理

### 队列任务示例

```php
// 发送邮件任务
class SendEmailJob implements ShouldQueue
{
    public function handle()
    {
        Mail::to($this->user)->send(new WelcomeEmail());
    }
}

// 分发任务
SendEmailJob::dispatch($user);
```

### 队列配置

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
    ],
],
```

---

## 📝 日志记录

### 日志级别

- **emergency**: 系统不可用
- **alert**: 必须立即采取行动
- **critical**: 严重错误
- **error**: 运行时错误
- **warning**: 警告信息
- **notice**: 正常但重要的信息
- **info**: 一般信息
- **debug**: 调试信息

### 日志使用

```php
// 记录信息
Log::info('User logged in', ['user_id' => $userId]);

// 记录错误
Log::error('Database connection failed', [
    'exception' => $e->getMessage()
]);

// 记录调试信息
Log::debug('API Request', [
    'url' => $request->fullUrl(),
    'method' => $request->method()
]);
```

---

## 📊 监控指标

### 性能监控

- **响应时间**: API平均响应时间
- **吞吐量**: 每秒请求数
- **错误率**: 错误请求占比
- **资源使用**: CPU、内存、磁盘

### 健康检查

```php
// 健康检查端点
Route::get('/api/health', function () {
    return response()->json([
        'status' => 'healthy',
        'database' => DB::connection()->getPdo() ? 'ok' : 'error',
        'redis' => Redis::ping() ? 'ok' : 'error',
        'timestamp' => now()->toISOString()
    ]);
});
```

---

## 🔗 相关文档

- [部署运维](../../06-部署运维/README.md)
- [核心架构](../../02-核心架构/README.md)
- [服务层](../06-服务层/README.md)

---

**维护者**: 薛小川
**最后更新**: 2026-01-17
