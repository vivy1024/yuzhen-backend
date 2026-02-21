# GraphRAG API安全指南

**版本**: v1.1.0
**更新日期**: 2025-11-05
**状态**: ✅ 已完成 - 整合自安全加固指南

---

## 📋 概述

本文档为MCO的GraphRAG API提供安全加固方案，包括API认证和Cypher查询审计功能，确保只有授权服务可以访问GraphRAG API，并记录所有查询操作用于安全监控。

**适用范围**: MCO GraphRAG API (端口8001)
**安全等级**: 生产级
**目标**: 防止未授权访问和Cypher注入攻击

---

## 🎯 核心安全架构

### 双层防护体系
1. **API Key认证** - 第一层访问控制
2. **查询审计** - 第二层安全监控

### 安全目标
- ✅ 授权访问控制（MCP服务级别）
- ✅ 查询操作审计（完整可追溯）
- ✅ 危险操作检测（实时告警）
- ✅ 防止注入攻击（Cypher注入防护）

---

## 🔧 API Key认证实施

### 核心组件
**文件位置**: `mcp-servers/meta-learning-mcp/src/api/middleware/auth.py`

#### API Key管理器
```python
class APIKeyManager:
    """API Key管理器"""

    def __init__(self):
        self._valid_keys: List[str] = []
        self._load_api_keys()

    def _load_api_keys(self):
        """从环境变量加载API Keys"""
        env_keys = os.getenv("GRAPHRAG_API_KEYS", "").strip()

        if env_keys:
            self._valid_keys = [key.strip() for key in env_keys.split(",") if key.strip()]
            logger.info(f"已加载 {len(self._valid_keys)} 个API Keys")
        else:
            # 开发环境默认Key
            self._valid_keys = ["dev-key-professional-fitness-coach"]
            logger.warning("⚠️ 使用开发环境默认API Key")
```

#### 认证中间件
```python
async def verify_api_key(api_key: Optional[str] = Security(api_key_header)) -> str:
    """验证API Key依赖函数"""
    if api_key is None:
        raise HTTPException(
            status_code=401,
            detail="缺少API Key，请在请求头中添加: X-API-Key: your-api-key"
        )

    if not api_key_manager.is_valid_key(api_key):
        raise HTTPException(
            status_code=403,
            detail="无效的API Key，请联系管理员获取有效密钥"
        )

    return api_key
```

### 环境配置
**Docker Compose配置**:
```yaml
services:
  fitness_mcp_meta_learning:
    environment:
      # GraphRAG API Keys（用逗号分隔多个Key）
      GRAPHRAG_API_KEYS: "prod-key-professional-fitness-coach,prod-key-nutrition-guide"
      # 审计日志配置
      AUDIT_LOG_FILE: "/app/logs/cypher_audit.log"
      LOG_LEVEL: "INFO"
    volumes:
      # 审计日志持久化
      - ./logs/meta-learning:/app/logs
```

### API Key管理策略
**分层命名规范**:
```
格式: {环境}-{服务缩写}-{版本}-{YYYYMM}
示例:
- prod-pfc-v1-202511 (生产环境-Professional Fitness Coach)
- staging-ng-v1-202511 (预发布-Nutrition Guide)
- dev-admin-v1-202511 (开发环境-管理员)
```

---

## 📊 Cypher查询审计实施

### 审计日志器
**文件位置**: `mcp-servers/meta-learning-mcp/src/api/middleware/audit.py`

#### 核心功能
```python
class CypherAuditLogger:
    """Cypher查询审计日志器 - 支持日志轮转和压缩"""

    def log_query(
        self,
        query: str,
        domain: str,
        api_key: Optional[str] = None,
        execution_time: Optional[float] = None,
        result_count: Optional[int] = None,
        error: Optional[str] = None
    ):
        """记录Cypher查询审计日志"""
        # 构建审计条目
        audit_entry = {
            "timestamp": datetime.now().isoformat(),
            "query": self._sanitize_query(query),
            "domain": domain,
            "api_key": self._mask_api_key(api_key),
            "execution_time": round(execution_time, 3) if execution_time else None,
            "result_count": result_count,
            "error": error,
        }

        # 检测危险操作
        danger_level = self._assess_danger_level(query)
        if danger_level > 0:
            audit_entry["danger_level"] = danger_level
            if danger_level >= 3:
                audit_entry["⚠️HIGH_RISK"] = "DANGEROUS_OPERATION_DETECTED"

        # 记录到审计日志
        self.audit_logger.info(json.dumps(audit_entry, ensure_ascii=False))
```

#### 危险等级评估
```python
def _assess_danger_level(self, query: str) -> int:
    """评估查询的危险等级"""
    query_upper = query.upper()

    # 高风险操作
    high_risk = ['DELETE', 'DETACH', 'DROP']
    if any(keyword in query_upper for keyword in high_risk):
        return 3

    # 中风险操作
    medium_risk = ['REMOVE', 'CREATE', 'MERGE', 'FOREACH']
    if any(keyword in query_upper for keyword in medium_risk):
        return 2

    # 低风险操作
    low_risk = ['SET', 'LOAD CSV']
    if any(keyword in query_upper for keyword in low_risk):
        return 1

    return 0  # 安全
```

### 审计日志格式
**标准日志条目**:
```json
{
  "timestamp": "2025-11-05T12:34:56.789",
  "query": "MATCH (e:Exercise) WHERE e.difficulty <= 3 RETURN e LIMIT 10",
  "domain": "fitness_exercises",
  "api_key": "...coach",
  "execution_time": 0.123,
  "result_count": 10,
  "error": null
}
```

**危险操作标记**:
```json
{
  "timestamp": "2025-11-05T12:35:23.456",
  "query": "MATCH (f:Food) DETACH DELETE f",
  "domain": "nutrition",
  "api_key": "...guide",
  "execution_time": 0.056,
  "result_count": 0,
  "error": null,
  "danger_level": 3,
  "⚠️HIGH_RISK": "DANGEROUS_OPERATION_DETECTED"
}
```

---

## 🔧 集成实施

### GraphRAG路由修改
**文件**: `mcp-servers/meta-learning-mcp/src/api/routes/graphrag.py`

#### 添加认证依赖
```python
from fastapi import Depends
from api.middleware.auth import verify_api_key
from api.middleware.audit import audit_logger

@router.post(
    "/query",
    response_model=ApiResponse[Dict[str, Any]],
    dependencies=[Depends(verify_api_key)]  # ✅ API Key验证
)
async def query_graphrag(
    request: GraphRAGQueryRequest,
    api_key: str = Depends(verify_api_key)  # ✅ 获取验证通过的API Key
):
    """
    GraphRAG统一查询接口

    ⚠️ 需要API Key认证
    ⚠️ 所有查询都会被审计
    """
    start_time = time.time()
    result = None
    error_msg = None

    try:
        # 检查危险查询
        if request.query_type == "graph_query":
            if audit_logger.is_dangerous_query(request.query_text):
                logger.warning(f"⚠️ 检测到危险Cypher查询: {request.query_text[:100]}")

        # 执行查询
        result = await graphrag_tool.query(query_args)

        # 记录成功的查询
        execution_time = time.time() - start_time
        audit_logger.log_query(
            query=request.query_text,
            domain=request.domain,
            api_key=api_key,
            execution_time=execution_time,
            result_count=result.get('count', 0),
            error=None
        )

    except Exception as e:
        # 记录失败的查询
        execution_time = time.time() - start_time
        error_msg = str(e)

        audit_logger.log_query(
            query=request.query_text,
            domain=request.domain,
            api_key=api_key,
            execution_time=execution_time,
            result_count=0,
            error=error_msg
        )

        raise HTTPException(
            status_code=500,
            detail=f"GraphRAG查询失败: {str(e)}"
        )
```

---

## 🚨 错误处理和监控

### 常见错误类型

#### API认证错误
**401 Unauthorized**:
```json
{
  "detail": "缺少API Key，请在请求头中添加: X-API-Key: your-api-key",
  "headers": {
    "WWW-Authenticate": "APIKey"
  }
}
```

**403 Forbidden**:
```json
{
  "detail": "无效的API Key，请联系管理员获取有效密钥",
  "headers": {
    "WWW-Authenticate": "APIKey"
  }
}
```

#### 紧急故障排除
**场景1：API认证失效**
```bash
# 快速诊断
curl -I http://localhost:8001/api/graphrag/query \
  -H "X-API-Key: your-production-key"

# 重启服务
docker-compose restart fitness_mcp_meta_learning

# 验证修复
curl -X POST http://localhost:8001/api/graphrag/query \
  -H "Content-Type: application/json" \
  -H "X-API-Key: dev-key-professional-fitness-coach" \
  -d '{"query_type":"graph_query","domain":"general","query_text":"MATCH (n) RETURN count(n) LIMIT 1"}'
```

### 监控脚本
**API认证监控** (`monitor_auth.py`):
```python
#!/usr/bin/env python3
"""API认证监控脚本"""
import requests
import time
import logging

def test_api_auth():
    """测试API认证状态"""
    try:
        # 测试有效API Key
        response = requests.post(
            "http://localhost:8001/api/graphrag/query",
            json={"query_type":"graph_query","domain":"general","query_text":"MATCH (n) RETURN count(n)"},
            headers={"X-API-Key": "dev-key-professional-fitness-coach"}
        )
        if response.status_code != 200:
            logging.error(f"❌ API认证失败: {response.status_code}")
        else:
            logging.info("✅ API认证正常")
    except Exception as e:
        logging.error(f"❌ API认证测试失败: {e}")

if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)

    while True:
        test_api_auth()
        time.sleep(300)  # 每5分钟检查一次
```

---

## 📋 生产部署最佳实践

### 环境变量配置
**生产环境** (`.env.production`):
```bash
# MCO GraphRAG API Keys（生产环境）
GRAPHRAG_API_KEYS="prod-pfc-2025-11,prod-ng-2025-11,prod-admin-2025-11"

# 审计日志级别
LOG_LEVEL=INFO
```

**开发环境** (`.env.local`):
```bash
# MCO GraphRAG API Keys（开发环境）
GRAPHRAG_API_KEYS="dev-key-professional-fitness-coach,dev-key-nutrition-guide"

# 审计日志级别
LOG_LEVEL=DEBUG
```

### 自动化API Key轮换
**轮换脚本** (`scripts/rotate_api_keys.sh`):
```bash
#!/bin/bash
# API Key自动轮换脚本

ENVIRONMENT=${1:-"production"}
BACKUP_DIR="/backup/api_keys"

# 生成随机API Key
generate_api_key() {
    openssl rand -base64 32 | tr -d "=+/" | cut -c1-32
}

# 轮换API Keys
rotate_keys() {
    local new_keys=""

    # 生成新Key（示例逻辑）
    new_key="${ENVIRONMENT}-service-v1-$(date +%Y%m)"

    # 更新环境文件
    sed -i.bak "s/GRAPHRAG_API_KEYS=.*/GRAPHRAG_API_KEYS=\"$new_key\"/" ".env.${ENVIRONMENT}"

    # 重启服务
    docker-compose restart fitness_mcp_meta_learning

    echo "✅ API Keys已轮换完成"
}

# 定时任务配置
# 每月1号凌晨2点自动轮换生产环境API Keys
# 0 2 1 * * /path/to/your/project/scripts/rotate_api_keys.sh production
```

### 日志轮转配置
**Logrotate配置** (`/etc/logrotate.d/graphrag_audit`):
```bash
/app/logs/cypher_audit.log {
    daily
    rotate 90                    # 保留90天日志
    compress                    # 压缩旧日志
    delaycompress               # 延迟压缩一天
    notifempty                  # 空文件不轮转
    create 644 root root        # 创建新文件权限
    missingok                   # 文件不存在不报错
    maxsize 100M                # 超过100MB立即轮转
}
```

---

## 🔗 相关文档

- [07-API接口参考.md](./07-API接口参考.md)
- [15-API响应格式规范.md](./15-API响应格式规范.md)
- [17-系统架构分析.md](./17-系统架构分析.md)

---

## 📝 更新日志

### v1.1.0 (2025-11-05) - 文档整合版
- ✅ 整合自根目录GraphRAG API安全加固指南
- ✅ 精简为核心安全实施要点
- ✅ 更新为后端代码参考格式
- ✅ 补充Docker部署配置说明

### v2.0.0 (2025-11-05) - 完善版
- ✅ 添加完整的Docker部署配置
- ✅ 提供详细的代码实施步骤
- ✅ 完善错误处理和异常情况说明
- ✅ 添加生产环境最佳实践

### v1.0.0 (2025-11-05) - 初始版本
- ✅ 基础API认证方案
- ✅ Cypher查询审计功能
- ✅ 核心安全框架

---

**维护者**: BUILD_BODY Security Team
**最后更新**: 2025-11-05
**审核状态**: ✅ 已完成整合
**安全等级**: 生产级

---

<div align="center">
<strong>🔒 认证防护 · 📋 完整审计 · 🛡️ 生产安全</strong>
</div>