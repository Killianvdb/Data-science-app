<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dataset extends Model
{

    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'original_name',
        'path',
        'status',
        'rows',
        'columns',
    ];


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
