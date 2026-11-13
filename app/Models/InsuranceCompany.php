<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsuranceCompany extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'contact_person',
        'phone',
        'email',
        'status',
        'notes',
    ];

    /**
     * Scope for active insurance companies
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'Active']);
    }
}

