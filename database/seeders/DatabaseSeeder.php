<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 开始初始化数据库...');
        
        $this->call([
            // 1. 导入会员等级配置
            MembershipSeeder::class,
            
            // 2. 导入1603个动作数据（包含19236个媒体文件）
            OptimizedExercisesV2Importer::class,
        ]);
        
        $this->command->info('');
        $this->command->info('✅ 数据库初始化完成！');
        $this->command->info('');
    }
}
