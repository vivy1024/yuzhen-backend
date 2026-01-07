<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExportUserDataSeeder extends Seeder
{
    /**
     * 导出当前用户数据用于服务器导入
     */
    public function run(): void
    {
        $exportFile = storage_path('app/user_export.sql');
        $jsonFile = storage_path('app/user_export.json');
        
        echo "🔍 正在查找所有用户...\n";
        $users = DB::table('users')->get();
        
        if ($users->isEmpty()) {
            echo "⚠️ 未找到任何用户数据\n";
            return;
        }
        
        echo "✅ 找到 {$users->count()} 个用户\n\n";
        
        $sqlStatements = [];
        $jsonData = [];
        
        foreach ($users as $user) {
            echo "📋 导出用户: {$user->email} (ID: {$user->id})\n";
            
            // 1. 导出users表
            $userData = DB::table('users')->where('id', $user->id)->first();
            if ($userData) {
                $sqlStatements[] = $this->generateInsert('users', (array)$userData);
            }
            
            // 2. 导出user_profiles表
            $profile = DB::table('user_profiles')->where('user_id', $user->id)->first();
            if ($profile) {
                $sqlStatements[] = $this->generateInsert('user_profiles', (array)$profile);
                echo "  ✓ 用户档案已导出\n";
            }
            
            // 3. 导出user_memberships表
            $memberships = DB::table('user_memberships')->where('user_id', $user->id)->get();
            foreach ($memberships as $membership) {
                $sqlStatements[] = $this->generateInsert('user_memberships', (array)$membership);
            }
            if ($memberships->count() > 0) {
                echo "  ✓ 会员记录已导出 ({$memberships->count()}条)\n";
            }
            
            // 4. 导出training_plans表
            $plans = DB::table('training_plans')
                ->where(function($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhere('created_by', $user->id);
                })
                ->get();
            foreach ($plans as $plan) {
                $sqlStatements[] = $this->generateInsert('training_plans', (array)$plan);
            }
            if ($plans->count() > 0) {
                echo "  ✓ 训练计划已导出 ({$plans->count()}条)\n";
            }
            
            // 5. 导出training_sessions表
            $sessions = DB::table('training_sessions')
                ->whereIn('plan_id', $plans->pluck('id'))
                ->get();
            foreach ($sessions as $session) {
                $sqlStatements[] = $this->generateInsert('training_sessions', (array)$session);
            }
            if ($sessions->count() > 0) {
                echo "  ✓ 训练课程已导出 ({$sessions->count()}条)\n";
            }
            
            // 6. 导出training_records表
            $records = DB::table('training_records')
                ->where(function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->get();
            foreach ($records as $record) {
                $sqlStatements[] = $this->generateInsert('training_records', (array)$record);
            }
            if ($records->count() > 0) {
                echo "  ✓ 训练记录已导出 ({$records->count()}条)\n";
            }
            
            // 7. 导出training_progress表
            $progress = DB::table('training_progress')->where('user_id', $user->id)->get();
            foreach ($progress as $prog) {
                $sqlStatements[] = $this->generateInsert('training_progress', (array)$prog);
            }
            if ($progress->count() > 0) {
                echo "  ✓ 训练进度已导出 ({$progress->count()}条)\n";
            }
            
            // 8. 导出chat_sessions表
            $chats = DB::table('chat_sessions')->where('user_id', $user->id)->get();
            foreach ($chats as $chat) {
                $sqlStatements[] = $this->generateInsert('chat_sessions', (array)$chat);
            }
            if ($chats->count() > 0) {
                echo "  ✓ 聊天记录已导出 ({$chats->count()}条)\n";
            }
            
            // JSON格式导出（用于查看和备份）
            $jsonData[] = [
                'user' => $userData,
                'profile' => $profile,
                'memberships' => $memberships,
                'training_plans' => $plans,
                'training_sessions' => $sessions,
                'training_records' => $records,
                'training_progress' => $progress,
                'chat_sessions' => $chats,
            ];
            
            echo "\n";
        }
        
        // 生成SQL文件
        $sql = "-- BUILD_BODY 用户数据导出\n";
        $sql .= "-- 导出时间: " . now()->toDateTimeString() . "\n";
        $sql .= "-- 用户数量: {$users->count()}\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        $sql .= implode(";\n\n", $sqlStatements) . ";\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        file_put_contents($exportFile, $sql);
        echo "✅ SQL文件已生成: {$exportFile}\n";
        
        // 生成JSON文件
        file_put_contents($jsonFile, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "✅ JSON文件已生成: {$jsonFile}\n";
        
        echo "\n";
        echo "========================================\n";
        echo "📦 导出完成！\n";
        echo "========================================\n";
        echo "SQL导入命令:\n";
        echo "  mysql -u用户名 -p密码 数据库名 < user_export.sql\n";
        echo "\n";
        echo "或使用Laravel seeder:\n";
        echo "  php artisan db:seed --class=ImportUserDataSeeder\n";
        echo "========================================\n";
    }
    
    /**
     * 生成INSERT语句
     */
    private function generateInsert(string $table, array $data): string
    {
        $columns = array_keys($data);
        $values = array_map(function($value) {
            if (is_null($value)) {
                return 'NULL';
            }
            if (is_numeric($value)) {
                return $value;
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            // 转义单引号
            $escaped = str_replace("'", "''", $value);
            return "'{$escaped}'";
        }, array_values($data));
        
        $columnsStr = implode(', ', $columns);
        $valuesStr = implode(', ', $values);
        
        return "INSERT INTO `{$table}` ({$columnsStr}) VALUES ({$valuesStr})";
    }
}

