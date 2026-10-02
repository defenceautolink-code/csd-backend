<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'vehicle_type',
        'logo',
        'status',
    ];

    protected $casts = [
        'vehicle_type' => 'array',
    ];

    /**
     * Relationship: Brand has many Vehicle Models
     */
    public function models()
    {
        return $this->hasMany(VehicleModel::class, 'brand_id');
    }
}
