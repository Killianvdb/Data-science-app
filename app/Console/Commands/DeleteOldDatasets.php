<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Dataset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class DeleteOldDatasets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-old-datasets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete datasets older than 7 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
/*
        $cutoff = now()->subDays(7);

        $datasets = Dataset::where('created_at', '<', $cutoff)->get();

        foreach ($datasets as $dataset) {
            // we have to try it !!
            if ($dataset->file_path && Storage::exists($dataset->file_path)) {
                Storage::delete($dataset->file_path);
            }

            $dataset->delete();
        }

        $this->info("Deleted {$datasets->count()} old datasets.");

        return Command::SUCCESS;
*/
    }
}
