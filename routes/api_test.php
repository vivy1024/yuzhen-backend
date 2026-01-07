<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::get('/test/db-info', function () {
    try {
        // 获取数据库连接信息
        $connection = DB::connection();
        $config = $connection->getConfig();
        
        // 获取users表的列信息
        $columns = Schema::getColumnListing('users');
        
        // 测试查询一个用户
        $user = DB::table('users')->where('email', 'vivy@buildbudy.com')->first();
        
        return response()->json([
            'database' => $config['database'],
            'host' => $config['host'],
            'username' => $config['username'],
            'users_columns' => $columns,
            'has_phone_column' => in_array('phone', $columns),
            'has_name_column' => in_array('name', $columns),
            'has_username_column' => in_array('username', $columns),
            'test_user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

