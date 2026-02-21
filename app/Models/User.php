<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Plan;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'user_type',
        'phone_number',
        'is_active',
        'plan_id',
        'files_used_this_month',
    ];


    public function datasets() {
        return $this->hasMany(Dataset::class);
    }


    public function plan() {
        return $this->belongsTo(Plan::class);
    }


    public function canUpload(int $numberOfFiles = 1): bool
    {
        if (!$this->plan) {
            return false;
        }

        if ($this->plan->monthly_limit === null) {
            return true;
        }

        return ($this->files_used_this_month + $numberOfFiles)
            <= $this->plan->monthly_limit;
    }


    public function validateFiles(array $files): array
    {
        $plan = $this->plan;

        if (count($files) > $plan->max_files_per_transaction) {
            return ['error' => 'Too many files per transaction'];
        }

        foreach ($files as $file) {
            if ($file->getSize() > $plan->max_file_size_mb * 1024 * 1024) {
                return ['error' => 'File exceeds maximum size for your plan'];
            }
        }

        if (!$this->canUpload(count($files))) {
            return ['error' => 'Monthly limit reached. Upgrade required'];
        }

        return ['success' => true];
    }


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
