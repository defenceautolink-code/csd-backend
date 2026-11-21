<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmPage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_pages';

    protected $fillable = [
        'title',
        'slug',
        'detail',
        'status',
        'position',
        'img',
    ];

    protected $casts = [
        'position' => 'integer',
    ];
}
