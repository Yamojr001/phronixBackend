<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'file_path',
        'release_notes',
        'downloads',
    ];
}
