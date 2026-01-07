# Laravel后端API分离实施方案

**创建日期**: 2025-01-02
**状态**: 📋 规划阶段
**目标**: 基于现有ChatSession实现，分离出话题管理、消息历史和训练计划导入API

---

## 1. 现状分析

### 1.1 现有数据结构

**ChatSession模型** (`app/Models/ChatSession.php`)
- 已有字段：session_id, user_id, user_query, llm_response, tools_used, metadata等
- 已有关系：belongsTo User
- 已有Scope：byUser, bySession, highQuality等

**现有API** (`routes/internal.php`)
```php
POST   /api/internal/chat/save-session          // 保存对话
POST   /api/internal/chat/update-feedback       // 更新反馈
GET    /api/internal/chat/user/{userId}/history // 获取历史
GET    /api/internal/chat/high-quality          // 高质量对话
```

### 1.2 问题分析

1. **缺少话题（Topic）概念**
   - 当前只有session_id，没有话题分组
   - 前端需要话题列表来组织对话

2. **API路由混乱**
   - internal路由用于DAML-RAG内部调用
   - 缺少面向前端的公开API

3. **训练计划数据分散**
   - 训练计划数据存储在metadata中
   - 没有独立的训练计划表和API

---

## 2. 数据库设计

### 2.1 新增Topics表

```php
Schema::create('chat_topics', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('name', 100);  // 话题名称
    $table->text('description')->nullable();  // 话题描述
    $table->integer('message_count')->default(0);  // 消息数量
    $table->text('last_message')->nullable();  // 最后一条消息
    $table->timestamp('last_message_at')->nullable();  // 最后消息时间
    $table->timestamps();
    $table->softDeletes();  // 软删除
    
    $table->index(['user_id', 'created_at']);
});
```

### 2.2 修改ChatSessions表

```php
Schema::table('chat_sessions', function (Blueprint $table) {
    $table->foreignId('topic_id')->nullable()
          ->after('user_id')
          ->constrained('chat_topics')
          ->onDelete('set null');
    
    $table->index(['topic_id', 'created_at']);
});
```

### 2.3 新增TrainingPlans表

```php
Schema::create('training_plans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('chat_session_id')->nullable()
          ->constrained()->onDelete('set null');  // 来源对话
    
    $table->string('name', 100);  // 计划名称
    $table->text('description')->nullable();  // 计划描述
    $table->integer('weeks');  // 训练周数
    $table->integer('frequency');  // 每周频率
    $table->json('exercises');  // 动作列表
    $table->json('target_muscles')->nullable();  // 目标肌群
    $table->json('safety_notes')->nullable();  // 安全提示
    $table->enum('difficulty', ['beginner', 'intermediate', 'advanced'])->nullable();
    $table->enum('status', ['active', 'completed', 'archived'])->default('active');
    
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['user_id', 'status', 'created_at']);
});
```

---

## 3. API路由设计

### 3.1 话题管理API

**路由文件**: `routes/api.php`

```php
Route::middleware('auth:sanctum')->prefix('chat')->group(function () {
    // 话题管理
    Route::get('/topics', [ChatTopicController::class, 'index']);
    Route::post('/topics', [ChatTopicController::class, 'store']);
    Route::get('/topics/{id}', [ChatTopicController::class, 'show']);
    Route::put('/topics/{id}', [ChatTopicController::class, 'update']);
    Route::delete('/topics/{id}', [ChatTopicController::class, 'destroy']);
    
    // 话题消息
    Route::get('/topics/{id}/messages', [ChatTopicController::class, 'messages']);
});
```

### 3.2 消息历史API

```php
Route::middleware('auth:sanctum')->prefix('chat')->group(function () {
    // 消息管理
    Route::get('/messages', [ChatMessageController::class, 'index']);
    Route::get('/messages/{id}', [ChatMessageController::class, 'show']);
    Route::post('/messages/{id}/rating', [ChatMessageController::class, 'rating']);
});
```

### 3.3 训练计划API

```php
Route::middleware('auth:sanctum')->prefix('training')->group(function () {
    // 训练计划管理
    Route::get('/plans', [TrainingPlanController::class, 'index']);
    Route::post('/plans/import', [TrainingPlanController::class, 'import']);
    Route::get('/plans/{id}', [TrainingPlanController::class, 'show']);
    Route::put('/plans/{id}', [TrainingPlanController::class, 'update']);
    Route::delete('/plans/{id}', [TrainingPlanController::class, 'destroy']);
    Route::post('/plans/{id}/export', [TrainingPlanController::class, 'export']);
});
```

---

## 4. 控制器实现

### 4.1 ChatTopicController

**文件**: `app/Http/Controllers/Api/ChatTopicController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatTopic;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatTopicController extends Controller
{
    /**
     * 获取话题列表
     * GET /api/chat/topics
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $topics = ChatTopic::where('user_id', $user->id)
            ->orderBy('last_message_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $topics->map(function ($topic) {
                return [
                    'id' => (string) $topic->id,
                    'name' => $topic->name,
                    'createdAt' => $topic->created_at->toIso8601String(),
                    'updatedAt' => $topic->updated_at->toIso8601String(),
                    'messageCount' => $topic->message_count,
                    'lastMessage' => $topic->last_message,
                ];
            })
        ]);
    }
    
    /**
     * 创建新话题
     * POST /api/chat/topics
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);
        
        $user = $request->user();
        
        $topic = ChatTopic::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'message_count' => 0,
        ]);
        
        return response()->json([
            'code' => 200,
            'msg' => '创建成功',
            'data' => [
                'id' => (string) $topic->id,
                'name' => $topic->name,
                'createdAt' => $topic->created_at->toIso8601String(),
                'updatedAt' => $topic->updated_at->toIso8601String(),
                'messageCount' => 0,
            ]
        ]);
    }
    
    /**
     * 删除话题
     * DELETE /api/chat/topics/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $topic = ChatTopic::where('user_id', $user->id)
            ->findOrFail($id);
        
        $topic->delete();
        
        return response()->json([
            'code' => 200,
            'msg' => '删除成功',
            'data' => null
        ]);
    }
    
    /**
     * 获取话题消息列表
     * GET /api/chat/topics/{id}/messages
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $topic = ChatTopic::where('user_id', $user->id)
            ->findOrFail($id);
        
        $messages = $topic->chatSessions()
            ->orderBy('created_at', 'asc')
            ->get();
        
        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $messages->map(function ($session) {
                return [
                    'id' => (string) $session->id,
                    'topicId' => (string) $session->topic_id,
                    'role' => 'assistant',  // 从ChatSession提取
                    'content' => $session->llm_response,
                    'timestamp' => $session->created_at->timestamp * 1000,
                    'toolCalls' => $this->extractToolCalls($session),
                    'trainingPlan' => $this->extractTrainingPlan($session),
                ];
            })
        ]);
    }
    
    private function extractToolCalls($session): ?array
    {
        // 从metadata中提取工具调用信息
        if (!isset($session->metadata['dag_execution'])) {
            return null;
        }
        
        // 实现工具调用提取逻辑
        return null;
    }
    
    private function extractTrainingPlan($session): ?array
    {
        // 从metadata中提取训练计划
        if (!isset($session->metadata['training_plan'])) {
            return null;
        }
        
        return $session->metadata['training_plan'];
    }
}
```

### 4.2 TrainingPlanController

**文件**: `app/Http/Controllers/Api/TrainingPlanController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingPlan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TrainingPlanController extends Controller
{
    /**
     * 导入训练计划
     * POST /api/training/plans/import
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'weeks' => 'required|integer|min:1|max:52',
            'frequency' => 'required|integer|min:1|max:7',
            'exercises' => 'required|array',
            'target_muscles' => 'nullable|array',
            'safety_notes' => 'nullable|array',
            'difficulty' => 'nullable|in:beginner,intermediate,advanced',
            'chat_session_id' => 'nullable|integer|exists:chat_sessions,id',
        ]);
        
        $user = $request->user();
        
        $plan = TrainingPlan::create([
            'user_id' => $user->id,
            'chat_session_id' => $validated['chat_session_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'weeks' => $validated['weeks'],
            'frequency' => $validated['frequency'],
            'exercises' => $validated['exercises'],
            'target_muscles' => $validated['target_muscles'] ?? null,
            'safety_notes' => $validated['safety_notes'] ?? null,
            'difficulty' => $validated['difficulty'] ?? null,
            'status' => 'active',
        ]);
        
        return response()->json([
            'code' => 200,
            'msg' => '导入成功',
            'data' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'created_at' => $plan->created_at->toIso8601String(),
            ]
        ]);
    }
    
    /**
     * 获取训练计划列表
     * GET /api/training/plans
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $plans = TrainingPlan::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $plans
        ]);
    }
}
```

---

## 5. 模型关系

### 5.1 ChatTopic模型

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatTopic extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'message_count',
        'last_message',
        'last_message_at',
    ];
    
    protected $casts = [
        'last_message_at' => 'datetime',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class, 'topic_id');
    }
    
    public function updateLastMessage(string $message)
    {
        $this->update([
            'last_message' => $message,
            'last_message_at' => now(),
        ]);
    }
    
    public function incrementMessageCount()
    {
        $this->increment('message_count');
    }
}
```

### 5.2 TrainingPlan模型

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingPlan extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'user_id',
        'chat_session_id',
        'name',
        'description',
        'weeks',
        'frequency',
        'exercises',
        'target_muscles',
        'safety_notes',
        'difficulty',
        'status',
    ];
    
    protected $casts = [
        'exercises' => 'array',
        'target_muscles' => 'array',
        'safety_notes' => 'array',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class);
    }
}
```

### 5.3 更新ChatSession模型

```php
// 添加关系
public function topic()
{
    return $this->belongsTo(ChatTopic::class, 'topic_id');
}

public function trainingPlans()
{
    return $this->hasMany(TrainingPlan::class);
}
```

---

## 6. 数据迁移

### 6.1 创建迁移文件

```bash
# 在Docker容器内执行
docker exec fitness_php_v2 php artisan make:migration create_chat_topics_table
docker exec fitness_php_v2 php artisan make:migration add_topic_id_to_chat_sessions_table
docker exec fitness_php_v2 php artisan make:migration create_training_plans_table
```

### 6.2 执行迁移

```bash
docker exec fitness_php_v2 php artisan migrate
```

---

## 7. 实施步骤

### 阶段1：数据库准备（1小时）
1. ✅ 创建迁移文件
2. ✅ 编写迁移代码
3. ✅ 执行迁移
4. ✅ 验证数据库结构

### 阶段2：模型和关系（1小时）
1. ✅ 创建ChatTopic模型
2. ✅ 创建TrainingPlan模型
3. ✅ 更新ChatSession模型关系
4. ✅ 编写单元测试

### 阶段3：控制器实现（2小时）
1. ✅ 实现ChatTopicController
2. ✅ 实现TrainingPlanController
3. ✅ 实现ChatMessageController
4. ✅ 添加路由

### 阶段4：前端对接（1小时）
1. ✅ 更新前端API调用
2. ✅ 移除localStorage临时方案
3. ✅ 测试完整流程

### 阶段5：测试和优化（1小时）
1. ✅ API测试
2. ✅ 性能优化
3. ✅ 文档更新

**预计总工期**: 6小时

---

## 8. 测试用例

### 8.1 话题管理测试

```bash
# 创建话题
curl -X POST http://localhost:8000/api/chat/topics \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"name": "增肌训练咨询"}'

# 获取话题列表
curl -X GET http://localhost:8000/api/chat/topics \
  -H "Authorization: Bearer {token}"

# 删除话题
curl -X DELETE http://localhost:8000/api/chat/topics/1 \
  -H "Authorization: Bearer {token}"
```

### 8.2 训练计划导入测试

```bash
curl -X POST http://localhost:8000/api/training/plans/import \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "8周增肌计划",
    "weeks": 8,
    "frequency": 4,
    "exercises": [...],
    "difficulty": "intermediate"
  }'
```

---

## 9. 注意事项

1. **数据迁移**：现有ChatSession数据的topic_id为NULL，需要考虑兼容性
2. **软删除**：话题和训练计划使用软删除，保留历史数据
3. **权限控制**：确保用户只能访问自己的数据
4. **性能优化**：添加适当的索引，优化查询性能
5. **事务处理**：导入训练计划时使用数据库事务

---

## 10. 后续优化

1. **话题自动命名**：根据第一条消息自动生成话题名称
2. **训练计划模板**：提供常用训练计划模板
3. **数据统计**：添加话题和训练计划的统计功能
4. **搜索功能**：支持话题和训练计划的搜索
5. **导出功能**：支持训练计划导出为PDF/Excel

---

**维护者**: AI助手
**审核状态**: 待人工审核
**实施状态**: 📋 规划阶段
