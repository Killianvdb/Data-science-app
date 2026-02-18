<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    //

    protected $fillable = [
        'slug',
        'name',
        'price',
        'monthly_limit',
        'max_file_size_mb',
        'max_files_per_transaction'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

}
