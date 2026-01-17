# 健康检查API文档

**状态**: ✅ 已完成
**版本**: v2.0.0
**更新日期**: 2026-01-17

---

## 📋 概述

玉珍健身后端提供完整的健康检查API，用于监控系统和各组件的运行状态。支持基础健康检查、组件状态检查和CORS配置检查，帮助运维团队快速定位问题。

### 核心特性

- ✅ **基础健康检查** - 快速检查服务是否运行
- ✅ **组件状态检查** - 检查MySQL、Redis、Neo4j、Qdrant、DAML-RAG等组件
- ✅ **响应时间监控** - 测量各组件的响应时间
- ✅ **整体健康评估** - 自动计算系统整体健康状态
- ✅ **CORS配置检查** - 验证跨域配置是否正确
- ✅ **无需认证** - 健康检查端点无需JWT Token

---

## 🔧 技术实现

### 1. 健康检查控制器 (`HealthCheckController.php`)

**核心功能**:
```php
class HealthCheckController extends Controller
{
    // 基础健康检查
    public function index(): JsonResponse
    
    // 组件健康状态检查
    public function components(): JsonResponse
    
    // CORS配置检查
    public function cors(): JsonResponse
    
    // 私有方法：检查各组件
    private function checkMySQL(): array
    private function checkRedis(): array
    private function checkNeo4j(): array
    private function checkQdrant(): array
    private function checkDamlRag(): array
    private function measureResponseTime(callable $callback): float
}
```

### 2. 组件检查逻辑

**MySQL检查**:
```php
private function checkMySQL(): array
{
    try {
        DB::connection()->getPdo();
        $version = DB::select('SELECT VERSION() as version')[0]->version;
        
        return [
            'status' => 'healthy',
            'message' => 'MySQL连接正常',
            'version' => $version,
            'response_time_ms' => $this->measureResponseTime(...)
        ];
    } catch (Exception $e) {
        return [
            'status' => 'unhealthy',
            'message' => 'MySQL连接失败',
            'error' => $e->getMessage()
        ];
    }
}
```

**Redis检查**:
```php
private function checkRedis(): array
{
    try {
        $startTime = microtime(true);
        Redis::ping();
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        
        return [
            'status' => 'healthy',
            'message' => 'Redis连接正常',
            'response_time_ms' => $responseTime
        ];
    } catch (Exception $e) {
        return ['status' => 'unhealthy', ...];
    }
}
```

**Neo4j检查**:
```php
private function checkNeo4j(): array
{
    try {
        $neo4jUrl = config('database.connections.neo4j.url');
        $response = Http::timeout(5)
            ->withBasicAuth($neo4jUser, $neo4jPassword)
            ->get($neo4jUrl);
        
        return [
            'status' => 'healthy',
            'message' => 'Neo4j连接正常',
            'response_time_ms' => $responseTime
        ];
    } catch (Exception $e) {
        return ['status' => 'unhealthy', ...];
    }
}
```

**Qdrant检查**:
```php
private function checkQdrant(): array
{
    try {
        $qdrantUrl = env('QDRANT_URL');
        $response = Http::timeout(5)->get("{$qdrantUrl}/");
        
        return [
            'status' => 'healthy',
            'message' => 'Qdrant连接正常',
            'response_time_ms' => $responseTime
        ];
    } catch (Exception $e) {
        return ['status' => 'unhealthy', ...];
    }
}
```

**DAML-RAG检查**:
```php
private function checkDamlRag(): array
{
    try {
        $damlRagUrl = env('DAML_RAG_URL');
        $response = Http::timeout(5)->get("{$damlRagUrl}/api/health");
        
        return [
            'status' => 'healthy',
            'message' => 'DAML-RAG服务正常',
            'response_time_ms' => $responseTime,
            'service_status' => $data['status']
        ];
    } catch (Exception $e) {
        return ['status' => 'unhealthy', ...];
    }
}
```

### 3. 整体健康状态计算

```php
$overallStatus = match(true) {
    $healthyCount === $totalCount => 'healthy',      // 所有组件正常
    $healthyCount > 0 => 'degraded',                 // 部分组件异常
    default => 'unhealthy'                           // 所有组件异常
};
```

---

## 📡 API端点

### 1. 基础健康检查

**端点**: `GET /api/health`

**描述**: 快速检查服务是否运行，用于负载均衡器和监控系统的健康探测。

**请求参数**: 无

**成功响应** (200):
```json
{
  "code": 200,
  "msg": "OK",
  "data": {
    "status": "healthy",
    "version": "2.0.0",
    "timestamp": "2026-01-17T10:30:00.000Z"
  }
}
```

**响应字段说明**:
| 字段 | 类型 | 说明 |
|------|------|------|
| status | string | 健康状态：healthy |
| version | string | 服务版本号 |
| timestamp | string | 检查时间（ISO 8601格式） |

**使用场景**:
- Zeabur健康探测
- 负载均衡器健康检查
- 监控系统心跳检测
- 快速验证服务是否启动

---

### 2. 组件健康状态检查

**端点**: `GET /api/health/components`

**描述**: 检查所有依赖组件的连接状态和响应时间，用于故障诊断和性能监控。

**请求参数**: 无

**成功响应** (200):
```json
{
  "code": 200,
  "msg": "OK",
  "data": {
    "status": "healthy",
    "timestamp": "2026-01-17T10:30:00.000Z",
    "components": {
      "mysql": {
        "status": "healthy",
        "message": "MySQL连接正常",
        "version": "8.0.35",
        "response_time_ms": 12.5
      },
      "redis": {
        "status": "healthy",
        "message": "Redis连接正常",
        "response_time_ms": 3.2
      },
      "neo4j": {
        "status": "healthy",
        "message": "Neo4j连接正常",
        "response_time_ms": 45.8
      },
      "qdrant": {
        "status": "healthy",
        "message": "Qdrant连接正常",
        "response_time_ms": 28.3
      },
      "daml_rag": {
        "status": "healthy",
        "message": "DAML-RAG服务正常",
        "response_time_ms": 156.7,
        "service_status": "healthy"
      }
    },
    "summary": {
      "total": 5,
      "healthy": 5,
      "unhealthy": 0
    }
  }
}
```

**组件状态说明**:
| 状态值 | 说明 | 含义 |
|--------|------|------|
| healthy | 健康 | 组件连接正常，响应正常 |
| unhealthy | 不健康 | 组件连接失败或响应异常 |
| unknown | 未知 | 组件未配置或无法检测 |

**整体状态说明**:
| 状态值 | 说明 | 条件 |
|--------|------|------|
| healthy | 健康 | 所有组件都正常 |
| degraded | 降级 | 部分组件异常，但核心功能可用 |
| unhealthy | 不健康 | 所有组件都异常 |

**组件异常响应示例**:
```json
{
  "code": 200,
  "msg": "OK",
  "data": {
    "status": "degraded",
    "timestamp": "2026-01-17T10:30:00.000Z",
    "components": {
      "mysql": {
        "status": "healthy",
        "message": "MySQL连接正常",
        "version": "8.0.35",
        "response_time_ms": 12.5
      },
      "redis": {
        "status": "unhealthy",
        "message": "Redis连接失败",
        "error": "Connection refused"
      },
      "neo4j": {
        "status": "unknown",
        "message": "Neo4j未配置"
      },
      "qdrant": {
        "status": "healthy",
        "message": "Qdrant连接正常",
        "response_time_ms": 28.3
      },
      "daml_rag": {
        "status": "unhealthy",
        "message": "DAML-RAG服务连接失败",
        "http_status": 503
      }
    },
    "summary": {
      "total": 5,
      "healthy": 2,
      "unhealthy": 3
    }
  }
}
```

**使用场景**:
- 故障诊断和定位
- 性能监控和优化
- 运维告警触发
- 系统健康报告

---

### 3. CORS配置检查

**端点**: `GET /api/health/cors`

**描述**: 检查跨域资源共享（CORS）配置，用于验证前端跨域请求配置是否正确。

**请求参数**: 无

**成功响应** (200):
```json
{
  "code": 200,
  "msg": "OK",
  "data": {
    "cors_allowed_origins": [
      "http://localhost:9000",
      "https://app.yuzhen-fitness.cn"
    ],
    "cors_allowed_origins_env": "http://localhost:9000,https://app.yuzhen-fitness.cn",
    "cors_paths": [
      "api/*"
    ],
    "cors_supports_credentials": true
  }
}
```

**响应字段说明**:
| 字段 | 类型 | 说明 |
|------|------|------|
| cors_allowed_origins | array | 允许的跨域源列表 |
| cors_allowed_origins_env | string | 环境变量中的跨域源配置 |
| cors_paths | array | 应用CORS的路径模式 |
| cors_supports_credentials | boolean | 是否支持携带凭证 |

**使用场景**:
- 前端跨域问题排查
- 验证生产环境CORS配置
- 安全审计和合规检查

---

## 🔄 工作流程

### 基础健康检查流程

```
监控系统/负载均衡器
    ↓
GET /api/health
    ↓
返回服务状态和版本
    ↓
监控系统记录结果
```

### 组件健康检查流程

```
运维人员/监控系统
    ↓
GET /api/health/components
    ↓
并行检查所有组件
├─ 检查MySQL连接
├─ 检查Redis连接
├─ 检查Neo4j连接
├─ 检查Qdrant连接
└─ 检查DAML-RAG服务
    ↓
计算整体健康状态
    ↓
返回详细检查结果
    ↓
运维人员分析和处理
```

### 组件检查详细流程

```
检查单个组件
    ↓
记录开始时间
    ↓
尝试连接组件
├─ 成功：记录版本和响应时间
└─ 失败：记录错误信息
    ↓
计算响应时间
    ↓
返回组件状态
```

---

## 🔐 安全机制

### 1. 无需认证

健康检查端点无需JWT Token认证，方便监控系统和负载均衡器访问。

**路由配置**:
```php
// 健康检查端点（无需认证）
Route::get('/health', [HealthCheckController::class, 'index']);
Route::get('/health/components', [HealthCheckController::class, 'components']);
Route::get('/health/cors', [HealthCheckController::class, 'cors']);
```

### 2. 超时保护

所有外部服务检查都设置5秒超时，避免长时间等待：

```php
$response = Http::timeout(5)->get($url);
```

### 3. 异常捕获

所有组件检查都包含异常捕获，确保单个组件失败不影响整体检查：

```php
try {
    // 检查组件
} catch (Exception $e) {
    return [
        'status' => 'unhealthy',
        'error' => $e->getMessage()
    ];
}
```

### 4. 敏感信息保护

错误信息不暴露敏感配置（如密码、密钥等），仅返回通用错误描述。

---

## 📊 监控集成

### 1. Zeabur健康探测

**配置示例**:
```yaml
# Zeabur健康检查配置
health_check:
  path: /api/health
  interval: 30s
  timeout: 5s
  unhealthy_threshold: 3
  healthy_threshold: 2
```

### 2. Prometheus监控

**指标采集示例**:
```python
# 采集健康检查指标
import requests
import time

def collect_health_metrics():
    response = requests.get('http://api.yuzhen-fitness.cn/api/health/components')
    data = response.json()['data']
    
    # 记录整体状态
    health_status = 1 if data['status'] == 'healthy' else 0
    
    # 记录各组件响应时间
    for component, status in data['components'].items():
        if 'response_time_ms' in status:
            response_time = status['response_time_ms']
            # 发送到Prometheus
```

### 3. 告警规则

**告警配置示例**:
```yaml
# 组件异常告警
- alert: ComponentUnhealthy
  expr: health_status == 0
  for: 5m
  annotations:
    summary: "组件健康检查失败"
    description: "{{ $labels.component }} 连接失败超过5分钟"

# 响应时间告警
- alert: SlowResponse
  expr: component_response_time_ms > 1000
  for: 10m
  annotations:
    summary: "组件响应时间过长"
    description: "{{ $labels.component }} 响应时间超过1秒"
```

---

## 🎯 前端集成示例

### 定期健康检查

```typescript
import api from '@/api/health'

// 定期检查系统健康状态
async function checkSystemHealth() {
  try {
    const response = await api.get('/health/components')
    
    if (response.data.status === 'healthy') {
      console.log('✅ 系统运行正常')
    } else if (response.data.status === 'degraded') {
      console.warn('⚠️ 系统部分功能异常')
      // 显示降级提示
      showWarning('部分功能可能受影响，我们正在处理')
    } else {
      console.error('❌ 系统异常')
      // 显示错误提示
      showError('系统暂时无法使用，请稍后再试')
    }
    
    // 记录各组件状态
    const components = response.data.components
    Object.entries(components).forEach(([name, status]) => {
      console.log(`${name}: ${status.status}`)
    })
  } catch (error) {
    console.error('健康检查失败', error)
  }
}

// 每30秒检查一次
setInterval(checkSystemHealth, 30000)
```

### 启动时健康检查

```typescript
// 应用启动时检查后端健康状态
async function initApp() {
  try {
    const response = await api.get('/health')
    
    if (response.data.status === 'healthy') {
      console.log('✅ 后端服务正常，版本:', response.data.version)
      // 继续初始化应用
      await loadUserData()
      await loadAppConfig()
    } else {
      throw new Error('后端服务异常')
    }
  } catch (error) {
    console.error('❌ 后端服务不可用')
    showError('无法连接到服务器，请检查网络连接')
  }
}
```

### 组件状态监控面板

```vue
<template>
  <div class="health-monitor">
    <h2>系统健康状态</h2>
    
    <div class="overall-status" :class="overallStatus">
      <span class="status-icon">{{ statusIcon }}</span>
      <span class="status-text">{{ statusText }}</span>
    </div>
    
    <div class="components-list">
      <div 
        v-for="(status, name) in components" 
        :key="name"
        class="component-item"
        :class="status.status"
      >
        <div class="component-name">{{ name }}</div>
        <div class="component-status">{{ status.message }}</div>
        <div v-if="status.response_time_ms" class="component-time">
          {{ status.response_time_ms }}ms
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/api/health'

const overallStatus = ref('healthy')
const components = ref({})

const statusIcon = computed(() => {
  return {
    'healthy': '✅',
    'degraded': '⚠️',
    'unhealthy': '❌'
  }[overallStatus.value]
})

const statusText = computed(() => {
  return {
    'healthy': '系统运行正常',
    'degraded': '部分功能异常',
    'unhealthy': '系统异常'
  }[overallStatus.value]
})

async function checkHealth() {
  const response = await api.get('/health/components')
  overallStatus.value = response.data.status
  components.value = response.data.components
}

onMounted(() => {
  checkHealth()
  setInterval(checkHealth, 30000)
})
</script>
```

---

## 🧪 测试验证

### 1. 基础健康检查测试

```bash
# 使用curl测试
curl http://localhost:8000/api/health

# 预期响应
{
  "code": 200,
  "msg": "OK",
  "data": {
    "status": "healthy",
    "version": "2.0.0",
    "timestamp": "2026-01-17T10:30:00.000Z"
  }
}
```

### 2. 组件健康检查测试

```bash
# 测试所有组件
curl http://localhost:8000/api/health/components

# 预期响应包含所有组件状态
```

### 3. CORS配置检查测试

```bash
# 测试CORS配置
curl http://localhost:8000/api/health/cors

# 预期响应包含CORS配置信息
```

### 4. 组件异常模拟测试

**模拟Redis异常**:
```bash
# 停止Redis服务
docker stop fitness_redis

# 检查健康状态
curl http://localhost:8000/api/health/components

# 预期：Redis状态为unhealthy，整体状态为degraded

# 恢复Redis服务
docker start fitness_redis
```

**模拟DAML-RAG异常**:
```bash
# 停止DAML-RAG服务
docker stop fitness_daml_rag

# 检查健康状态
curl http://localhost:8000/api/health/components

# 预期：daml_rag状态为unhealthy

# 恢复服务
docker start fitness_daml_rag
```

### 5. 响应时间测试

```bash
# 使用curl测量响应时间
curl -w "\nTime: %{time_total}s\n" http://localhost:8000/api/health/components

# 预期：总响应时间 < 1秒
```

---

## 📈 性能指标

### 响应时间基准

| 组件 | 正常响应时间 | 告警阈值 |
|------|-------------|---------|
| MySQL | < 20ms | > 100ms |
| Redis | < 10ms | > 50ms |
| Neo4j | < 50ms | > 200ms |
| Qdrant | < 50ms | > 200ms |
| DAML-RAG | < 200ms | > 1000ms |
| 整体检查 | < 500ms | > 2000ms |

### 可用性目标

- **基础健康检查**: 99.99%可用性
- **组件健康检查**: 99.9%可用性
- **单组件失败**: 不影响整体服务
- **多组件失败**: 触发告警和降级策略

---

## 🎉 后续优化方向

### 1. 监控增强
- [ ] 添加更多组件检查（Nginx、PHP-FPM等）
- [ ] 支持自定义健康检查规则
- [ ] 添加历史健康数据记录
- [ ] 生成健康趋势报告

### 2. 告警集成
- [ ] 集成钉钉/企业微信告警
- [ ] 支持邮件告警通知
- [ ] 添加告警升级机制
- [ ] 实现自动故障恢复

### 3. 性能优化
- [ ] 缓存健康检查结果（减少频繁检查）
- [ ] 异步并行检查组件
- [ ] 优化超时和重试策略
- [ ] 添加健康检查降级机制

### 4. 可视化
- [ ] 开发健康监控仪表板
- [ ] 实时健康状态展示
- [ ] 历史数据图表分析
- [ ] 告警事件时间线

---

## 📝 相关文档

- **API接口规范总览**: `yuzhen-backend/docs/05-API文档/01-API接口规范总览.md`
- **Zeabur部署指南**: `yuzhen-backend/docs/06-部署运维/Zeabur部署指南.md`
- **DAML-RAG健康检查**: `daml-rag-server/docs/05-API文档/01-健康检查API.md`

---

**维护者**: 薛小川
**最后更新**: 2026-01-17
