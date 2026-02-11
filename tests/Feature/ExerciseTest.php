<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Exercise功能测试
 * 
 * 测试动作库API端点的基本功能
 * 注意：使用RefreshDatabase会清空exercises表，
 * 因此测试只验证API响应格式，不依赖具体数据
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试获取动作列表 - 验证响应格式
     */
    public function test_can_get_exercise_list()
    {
        $response = $this->getJson('/api/exercises');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'code',
                     'msg',
                     'data' => [
                         'rows',
                         'total',
                         'page',
                         'per_page',
                     ]
                 ])
                 ->assertJson([
                     'code' => 200,
                 ]);
    }

    /**
     * 测试获取筛选选项 - 验证响应格式
     */
    public function test_can_get_filter_options()
    {
        $response = $this->getJson('/api/exercises/filter-options');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'code',
                     'msg',
                     'data',
                 ]);
    }

    /**
     * 测试获取不存在的动作详情返回404
     */
    public function test_can_get_exercise_detail()
    {
        // RefreshDatabase清空了exercises表，ID=1不存在，应返回404
        $response = $this->getJson('/api/exercises/1');

        $response->assertStatus(404);
    }

    /**
     * 测试筛选功能 - 验证响应格式
     */
    public function test_can_filter_exercises()
    {
        $response = $this->getJson('/api/exercises?muscle=chest&difficulty=Beginner');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'code',
                     'msg',
                     'data' => [
                         'rows',
                     ]
                 ]);
    }

    /**
     * 测试分页功能 - 验证响应格式
     */
    public function test_can_paginate_exercises()
    {
        $response = $this->getJson('/api/exercises?page=1&per_page=10');

        $response->assertStatus(200)
                 ->assertJsonPath('data.per_page', 10)
                 ->assertJsonPath('data.page', 1);
    }
}
