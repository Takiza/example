<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Archive extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'git_path',
        'archive_type_id'
    ];

    public function archive_type()
    {
        return $this->belongsTo(ArchiveType::class);
    }
}
