<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class AppVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'file_path', 'link',
        'release_notes',
        'downloads',
    ];
}
