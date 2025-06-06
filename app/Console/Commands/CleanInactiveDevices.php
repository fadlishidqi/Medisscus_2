<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class CleanInactiveDevices extends Command
{
    protected $signature = 'auth:clean-devices {--days=30 : Number of days to consider device inactive}';
    protected $description = 'Clean inactive devices from users table';

    public function handle()
    {
        $days = $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);
        
        $this->info("Cleaning devices inactive for more than {$days} days...");
        
        $updatedCount = User::where('last_login_at', '<', $cutoffDate)
            ->whereNotNull('device_id')
            ->update([
                'device_id' => null,
                'device_name' => null,
                'user_agent' => null
            ]);
        
        $this->info("Cleaned {$updatedCount} inactive devices.");
        
        return Command::SUCCESS;
    }
}