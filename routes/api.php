<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/**
 * API Routes
 * 
 * 主路由入口，加载各模块路由
 * 
 * @version 2.0.0
 */

/*
|--------------------------------------------------------------------------
| 健康检查
|--------------------------------------------------------------------------
*/
Route::get('/health', [\App\Http\Controllers\HealthCheckController::class, 'index']);
Route::get('/health/components', [\App\Http\Controllers\HealthCheckController::class, 'components']);
Route::get('/health/cors', [\App\Http\Controllers\HealthCheckController::class, 'cors']);

/*
|--------------------------------------------------------------------------
| 数据库测试（临时）
|--------------------------------------------------------------------------
*/
Route::get('/test/check-exercise-data', function () {
    try {
        $total = DB::table('exercises')->count();
        $hasData = DB::table('exercises')->whereNotNull('primary_muscle')->count();
        $nullData = DB::table('exercises')->whereNull('primary_muscle')->count();
        $sample = DB::table('exercises')->select('id', 'name_zh', 'primary_muscle', 'equipment')->limit(5)->get();
        
        return response()->json([
            'code' => 200,
            'data' => [
                'total_exercises' => $total,
                'has_primary_muscle_data' => $hasData,
                'null_primary_muscle' => $nullData,
                'sample_data' => $sample,
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json(['code' => 500, 'error' => $e->getMessage()], 500);
    }
});

Route::post('/test/fix-exercises', function () {
    try {
        $results = [];
        
        // 重命名primary_muscles为primary_muscle
        $has_plural = collect(DB::select("SHOW COLUMNS FROM exercises WHERE Field = 'primary_muscles'"))->isNotEmpty();
        if ($has_plural) {
            DB::statement("ALTER TABLE exercises CHANGE COLUMN primary_muscles primary_muscle VARCHAR(100)");
            $results[] = "✅ Renamed: primary_muscles → primary_muscle";
        } else {
            $results[] = "⚠️ Already renamed or doesn't exist";
        }
        
        // 验证修复
        $columns = collect(DB::select('SHOW COLUMNS FROM exercises'))->pluck('Field')->toArray();
        $results[] = "✅ Verification: has_primary_muscle = " . (in_array('primary_muscle', $columns) ? 'YES' : 'NO');
        
        return response()->json(['code' => 200, 'results' => $results]);
    } catch (\Exception $e) {
        return response()->json(['code' => 500, 'error' => $e->getMessage()], 500);
    }
});

Route::post('/test/fix-users-table', function () {
    try {
        $columns = [
            ['name' => 'phone', 'sql' => "ALTER TABLE users ADD COLUMN phone VARCHAR(255) NULL AFTER email"],
            ['name' => 'avatar', 'sql' => "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER phone"],
            ['name' => 'role', 'sql' => "ALTER TABLE users ADD COLUMN role VARCHAR(255) DEFAULT 'user' AFTER avatar"],
            ['name' => 'status', 'sql' => "ALTER TABLE users ADD COLUMN status CHAR(1) DEFAULT '1' AFTER role"],
            ['name' => 'del_flag', 'sql' => "ALTER TABLE users ADD COLUMN del_flag CHAR(1) DEFAULT '0' AFTER status"],
            ['name' => 'is_active', 'sql' => "ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER del_flag"],
            ['name' => 'last_login_at', 'sql' => "ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL AFTER is_active"],
        ];
        
        $results = [];
        foreach ($columns as $col) {
            try {
                $exists = collect(DB::select("SHOW COLUMNS FROM users WHERE Field = ?", [$col['name']]))->isNotEmpty();
                if (!$exists) {
                    DB::statement($col['sql']);
                    $results[] = "✅ Added: {$col['name']}";
                } else {
                    $results[] = "⚠️ Exists: {$col['name']}";
                }
            } catch (\Exception $e) {
                $results[] = "❌ Error on {$col['name']}: " . $e->getMessage();
            }
        }
        
        // 创建vivy用户
        $user = DB::table('users')->where('email', 'vivy@buildbudy.com')->first();
        if (!$user) {
            DB::table('users')->insert([
                'name' => 'Vivy',
                'email' => 'vivy@buildbudy.com',
                'password' => password_hash('password123', PASSWORD_BCRYPT),
                'role' => 'user',
                'status' => '1',
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $results[] = "✅ Created user: vivy@buildbudy.com";
        } else {
            $results[] = "⚠️ User exists: vivy@buildbudy.com";
        }
        
        return response()->json(['code' => 200, 'results' => $results]);
    } catch (\Exception $e) {
        return response()->json(['code' => 500, 'error' => $e->getMessage()], 500);
    }
});

Route::get('/test/db-info', function () {
    try {
        $connection = DB::connection();
        $config = $connection->getConfig();
        
        // 直接用SQL查询字段，不用Schema缓存
        $columns = collect(DB::select('SHOW COLUMNS FROM users'))->pluck('Field')->toArray();
        $user = DB::table('users')->where('email', 'vivy@buildbudy.com')->first();
        
        return response()->json([
            'code' => 200,
            'data' => [
                'database' => $config['database'],
                'host' => $config['host'],
                'username' => $config['username'],
                'users_columns' => $columns,
                'columns_count' => count($columns),
                'has_phone' => in_array('phone', $columns),
                'has_name' => in_array('name', $columns),
                'test_user' => $user ? [
                    'id' => $user->id, 
                    'name' => $user->name, 
                    'email' => $user->email,
                    'role' => $user->role ?? null,
                    'status' => $user->status ?? null,
                ] : null,
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json(['code' => 500, 'error' => $e->getMessage()], 500);
    }
});

/*
|--------------------------------------------------------------------------
| 数据迁移（临时 - 生产环境使用后删除）
|--------------------------------------------------------------------------
*/
Route::prefix('migrate')->group(function () {
    // 肌肉字段迁移预览
    Route::get('/muscles/preview', function () {
        try {
            $stats = [
                'total' => DB::table('exercises')->count(),
                'has_primary_muscle_zh' => DB::table('exercises')
                    ->whereNotNull('primary_muscle_zh')
                    ->count(),
                'has_muscles_primary_zh' => DB::table('exercises')
                    ->whereNotNull('muscles_primary_zh')
                    ->whereRaw('JSON_LENGTH(muscles_primary_zh) > 0')
                    ->count(),
                'needs_migration' => DB::table('exercises')
                    ->whereNotNull('primary_muscle_zh')
                    ->where(function($q) {
                        $q->whereNull('muscles_primary_zh')
                          ->orWhereRaw('JSON_LENGTH(muscles_primary_zh) = 0');
                    })
                    ->count(),
            ];
            
            return response()->json([
                'code' => 200,
                'msg' => '预览成功',
                'data' => [
                    'mode' => 'preview',
                    'stats' => $stats,
                    'message' => '这是预览模式，不会修改数据',
                    'next_step' => '如果统计信息正确，请调用 POST /api/migrate/muscles/execute 执行迁移'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'msg' => '预览失败', 'error' => $e->getMessage()], 500);
        }
    });
    
    // 执行肌肉字段迁移
    Route::post('/muscles/execute', function () {
        try {
            // 获取迁移前统计
            $beforeStats = [
                'total' => DB::table('exercises')->count(),
                'has_primary_muscle_zh' => DB::table('exercises')->whereNotNull('primary_muscle_zh')->count(),
                'has_muscles_primary_zh' => DB::table('exercises')
                    ->whereNotNull('muscles_primary_zh')
                    ->whereRaw('JSON_LENGTH(muscles_primary_zh) > 0')
                    ->count(),
            ];
            
            // 执行迁移
            DB::beginTransaction();
            
            // 1. 迁移 primary_muscle_zh → muscles_primary_zh
            DB::statement("
                UPDATE exercises 
                SET muscles_primary_zh = JSON_ARRAY(primary_muscle_zh)
                WHERE primary_muscle_zh IS NOT NULL 
                AND primary_muscle_zh != ''
                AND (muscles_primary_zh IS NULL OR JSON_LENGTH(muscles_primary_zh) = 0)
            ");
            
            // 2. 迁移 primary_muscle_en → muscles_primary_en
            DB::statement("
                UPDATE exercises 
                SET muscles_primary_en = JSON_ARRAY(primary_muscle_en)
                WHERE primary_muscle_en IS NOT NULL 
                AND primary_muscle_en != ''
                AND (muscles_primary_en IS NULL OR JSON_LENGTH(muscles_primary_en) = 0)
            ");
            
            // 3. 填充 all_muscles_zh
            DB::statement("
                UPDATE exercises 
                SET all_muscles_zh = JSON_ARRAY(primary_muscle_zh)
                WHERE primary_muscle_zh IS NOT NULL 
                AND primary_muscle_zh != ''
                AND (all_muscles_zh IS NULL OR JSON_LENGTH(all_muscles_zh) = 0)
            ");
            
            DB::commit();
            
            // 获取迁移后统计
            $afterStats = [
                'total' => DB::table('exercises')->count(),
                'has_primary_muscle_zh' => DB::table('exercises')->whereNotNull('primary_muscle_zh')->count(),
                'has_muscles_primary_zh' => DB::table('exercises')
                    ->whereNotNull('muscles_primary_zh')
                    ->whereRaw('JSON_LENGTH(muscles_primary_zh) > 0')
                    ->count(),
            ];
            
            // 抽样检查
            $sample = DB::table('exercises')
                ->whereNotNull('muscles_primary_zh')
                ->whereRaw('JSON_LENGTH(muscles_primary_zh) > 0')
                ->first(['id', 'name_zh', 'primary_muscle_zh', 'muscles_primary_zh']);
            
            return response()->json([
                'code' => 200,
                'msg' => '✅ 肌肉字段迁移成功',
                'data' => [
                    'before' => $beforeStats,
                    'after' => $afterStats,
                    'sample' => $sample,
                    'message' => '迁移成功完成'
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['code' => 500, 'msg' => '迁移失败', 'error' => $e->getMessage()], 500);
        }
    });
    
    // 验证迁移结果
    Route::get('/muscles/verify', function () {
        try {
            $stats = [
                'total' => DB::table('exercises')->count(),
                'has_primary_muscle_zh' => DB::table('exercises')->whereNotNull('primary_muscle_zh')->count(),
                'has_muscles_primary_zh' => DB::table('exercises')
                    ->whereNotNull('muscles_primary_zh')
                    ->whereRaw('JSON_LENGTH(muscles_primary_zh) > 0')
                    ->count(),
                'needs_migration' => DB::table('exercises')
                    ->whereNotNull('primary_muscle_zh')
                    ->where(function($q) {
                        $q->whereNull('muscles_primary_zh')
                          ->orWhereRaw('JSON_LENGTH(muscles_primary_zh) = 0');
                    })
                    ->count(),
            ];
            
            // 抽样检查（5条记录）
            $samples = DB::table('exercises')
                ->whereNotNull('muscles_primary_zh')
                ->whereRaw('JSON_LENGTH(muscles_primary_zh) > 0')
                ->limit(5)
                ->get(['id', 'name_zh', 'primary_muscle_zh', 'muscles_primary_zh']);
            
            $success = $stats['needs_migration'] === 0;
            
            return response()->json([
                'code' => 200,
                'msg' => '验证完成',
                'data' => [
                    'success' => $success,
                    'stats' => $stats,
                    'samples' => $samples,
                    'message' => $success ? '所有记录已成功迁移' : "仍有 {$stats['needs_migration']} 条记录未迁移"
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'msg' => '验证失败', 'error' => $e->getMessage()], 500);
        }
    });
});

/*
|--------------------------------------------------------------------------
| 用户信息（需要认证）
|--------------------------------------------------------------------------
*/
Route::middleware('jwt.auth')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| 模块路由
|--------------------------------------------------------------------------
*/

// Exercise模块
require __DIR__.'/modules/exercise.php';

// User模块 ✅
require __DIR__.'/modules/user.php';

// Auth模块 ✅
require __DIR__.'/modules/auth.php';

// Training模块 ✅
require __DIR__.'/modules/training.php';

// Training Record模块（力量进步追踪）✅
require __DIR__.'/modules/training-record.php';

// Training Log模块（闭环学习系统）✅
require __DIR__.'/modules/training-log.php';

// Personal Best模块（个人最佳记录）✅
require __DIR__.'/modules/personal-best.php';

// Social Login模块 ✅
require __DIR__.'/modules/social.php';

// Membership模块 ✅
require __DIR__.'/modules/membership.php';

// Usage模块（用量管理）✅
require __DIR__.'/modules/usage.php';

// Internal API（MCP/CrewAI访问）✅
require __DIR__.'/internal.php';

// MCP Tools API（前端MCP工具调用代理）✅
require __DIR__.'/modules/mcp-tools.php';

// Quality Rating模块（三轨评分系统）✅
require __DIR__.'/modules/quality-rating.php';

// Personalization Grading模块（个性化分级系统）✅
Route::prefix('v2/personalization')->group(function () {
    require __DIR__.'/modules/personalization_grading.php';
});

// Chat Topic模块（AI聊天话题管理）✅
require __DIR__.'/modules/chat-topic.php';

// Training Plan模块（训练计划导入）✅
require __DIR__.'/modules/training-plan.php';

// Food模块（食物库）✅
require __DIR__.'/modules/food.php';

// Admin模块（管理员后台）✅
require __DIR__.'/modules/admin.php';

// Progress模块（进度追踪）✅
require __DIR__.'/modules/progress.php';

// Feedback模块（用户反馈）✅
require __DIR__.'/modules/feedback.php';

// Help模块（帮助中心）✅
require __DIR__.'/modules/help.php';

// AI代理模块已移至 routes/web.php（无/api前缀）
// require __DIR__.'/modules/ai-proxy.php';

/*
|--------------------------------------------------------------------------
| AI代理路由（直接在api.php中定义，避免CORS问题）
|--------------------------------------------------------------------------
| 前端请求: /api/ai/v1/chat/stream
| 代理到: DAML-RAG服务
| 
| 注意：使用api中间件组，自动处理CORS，无需CSRF验证
*/
Route::prefix('ai')->group(function () {
    // 流式聊天接口
    Route::post('/v1/chat/stream', [\App\Http\Controllers\AiProxyController::class, 'streamChat']);
    
    // 非流式聊天接口
    Route::post('/v1/chat', [\App\Http\Controllers\AiProxyController::class, 'chat']);
    
    // 健康检查
    Route::get('/health', [\App\Http\Controllers\AiProxyController::class, 'health']);
});
