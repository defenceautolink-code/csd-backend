<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Blog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'blogs';

    protected $fillable = [
        'title',
        'slug',
        'details',
        'img',
        'publish_date',
        'status',
        'views',
        'likes',
        'shares',
    ];

    protected $casts = [
        'publish_date' => 'date:Y-m-d',
        'views' => 'integer',
        'likes' => 'integer',
        'shares' => 'integer',
    ];
}
