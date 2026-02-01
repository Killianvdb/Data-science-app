<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CleaningOperation extends Model
{
    public function dataset() {
        return $this->belongsTo(Dataset::class);
    }
}
