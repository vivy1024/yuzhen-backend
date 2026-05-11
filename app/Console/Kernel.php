<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 每月 1 日 00:00 重置积分使用量
        $schedule->command('credits:reset-monthly')
            ->monthlyOn(1, '00:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/credits-reset.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
