<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleVariant extends Model
{
    use HasFactory;

    protected $table = 'vehicle_variants';

    protected $fillable = [
        'brand_id',
        'model_id',
        'name',
        'price',
        'status',
    ];

    protected $casts = [
        'price' => 'float',
    ];

    /**
     * Relationship: Variant belongs to a Brand
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Relationship: Variant belongs to a Model
     */
    public function model()
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }
}
