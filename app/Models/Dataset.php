<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dataset extends Model
{

    public function user() {
    return $this->belongsTo(User::class);
    }
    public function columns() {
        return $this->hasMany(Column::class);
    }
    public function dataRows() {
        return $this->hasMany(DataRow::class);
    }
    public function cleaningOperations() {
        return $this->hasMany(CleaningOperation::class);
    }

}
