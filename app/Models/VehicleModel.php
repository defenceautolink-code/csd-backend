<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleModel extends Model
{
    use HasFactory;

    protected $table = 'vehicle_models';

    protected $fillable = [
        'brand_id',
        'name',
        'vehicle_segment',
        'status',
    ];

    /**
     * Relationship: Model belongs to a Brand
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Relationship: Model has many Variants
     */
    public function variants()
    {
        return $this->hasMany(VehicleVariant::class, 'model_id');
    }
}
