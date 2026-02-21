<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class ResetMonthlyUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-monthly-usage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset files_used_this_month for all users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        //

        User::query()->update([
            'files_used_this_month' => 0,
        ]);

        $this->info('Monthly usage reset successfully.');

        return Command::SUCCESS;
    }


}
