<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRow extends Model
{
    public function dataset() {
        return $this->belongsTo(Dataset::class);
    }
}
