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
            
            // 2. 导入1790个动作数据（适配新表结构 name_en/name_zh）
            ExercisesV2Importer::class,
            
            // 3. 导入FAQ数据
            FaqSeeder::class,
        ]);
        
        $this->command->info('');
        $this->command->info('✅ 数据库初始化完成！');
        $this->command->info('📝 注意：foods数据需要单独运行脚本导入');
        $this->command->info('   docker exec fitness_php_v2 php /var/www/html/scripts/import_foods_to_mysql.php');
        $this->command->info('');
    }
}
