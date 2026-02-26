<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineJob extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'pipeline_mode',
        'status',
        'current_step',
        'progress_pct',
        'result_json',
        'error_message',
    ];

    // ── Steps with labels and percentages ─────────────────────────────────────
    const STEPS = [
        'uploading'          => ['label' => 'Saving files…',              'pct' => 5],
        'cleaning'           => ['label' => 'Cleaning & standardising…',  'pct' => 25],
        'cross_referencing'  => ['label' => 'Merging reference files…',   'pct' => 50],
        'enriching'          => ['label' => 'AI enrichment…',             'pct' => 75],
        'generating_report'  => ['label' => 'Generating PDF report…',     'pct' => 90],
        'done'               => ['label' => 'Complete',                   'pct' => 100],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setStep(string $step): void
    {
        $this->update([
            'current_step' => $step,
            'progress_pct' => self::STEPS[$step]['pct'] ?? $this->progress_pct,
            'status'       => $step === 'done' ? 'done' : 'running',
        ]);
    }

    public function fail(string $message): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $message,
        ]);
    }

    public function toStatusArray(): array
    {
        $stepInfo = self::STEPS[$this->current_step] ?? ['label' => 'Processing…', 'pct' => $this->progress_pct];

        return [
            'status'       => $this->status,
            'step'         => $this->current_step,
            'step_label'   => $stepInfo['label'],
            'pct'          => $this->progress_pct,
            'error'        => $this->error_message,
            'result'       => $this->result_json ? json_decode($this->result_json, true) : null,
        ];
    }
}