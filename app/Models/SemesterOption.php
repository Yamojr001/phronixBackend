<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SemesterOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'spill',
        'name',
    ];
}
