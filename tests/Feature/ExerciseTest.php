<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Exercise功能测试
 * 
 * @version 1.0.0
 * @date 2025-11-01
 */
class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试获取动作列表
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
                         'pagination'
                     ]
                 ])
                 ->assertJson([
                     'code' => 200,
                     'msg' => '获取成功'
                 ]);
    }

    /**
     * 测试获取筛选选项
     */
    public function test_can_get_filter_options()
    {
        $response = $this->getJson('/api/exercises/filter-options');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'code',
                     'msg',
                     'data' => [
                         'muscles',
                         'equipment',
                         'difficulties'
                     ]
                 ]);
    }

    /**
     * 测试获取动作详情
     */
    public function test_can_get_exercise_detail()
    {
        // 假设ID为1的动作存在
        $response = $this->getJson('/api/exercises/1');

        $response->assertStatus(200)
                 ->assertJson([
                     'code' => 200,
                     'msg' => '获取成功'
                 ])
                 ->assertJsonStructure([
                     'data' => [
                         'id',
                         'name',
                         'difficulty'
                     ]
                 ]);
    }

    /**
     * 测试筛选功能
     */
    public function test_can_filter_exercises()
    {
        $response = $this->getJson('/api/exercises?muscle=chest&difficulty=beginner');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'code',
                     'msg',
                     'data' => [
                         'rows',
                         'pagination'
                     ]
                 ]);
    }

    /**
     * 测试分页功能
     */
    public function test_can_paginate_exercises()
    {
        $response = $this->getJson('/api/exercises?page=1&per_page=10');

        $response->assertStatus(200)
                 ->assertJsonPath('data.pagination.per_page', 10)
                 ->assertJsonPath('data.pagination.page', 1);
    }
}












