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
        'previous_price',
        'ex_showroom_price',
        'rto_road_tax',
        'insurance',
        'fastag_logistics',
        'on_road_price',
        'status',
    ];

    protected $casts = [
        'price' => 'float',
        'previous_price' => 'float',
        'ex_showroom_price' => 'float',
        'rto_road_tax' => 'float',
        'insurance' => 'float',
        'fastag_logistics' => 'float',
        'on_road_price' => 'float',
    ];

    protected $appends = [
        'calculated_on_road_price',
    ];

    /**
     * Dynamic Accessor: Calculate Total On-Road Price from components
     */
    public function getCalculatedOnRoadPriceAttribute(): float
    {
        $exShowroom = (float) ($this->ex_showroom_price > 0 ? $this->ex_showroom_price : $this->price);
        $rto = (float) ($this->rto_road_tax > 0 ? $this->rto_road_tax : round($exShowroom * 0.10, 2));
        $insurance = (float) ($this->insurance > 0 ? $this->insurance : 68000.00);
        $fastag = (float) ($this->fastag_logistics > 0 ? $this->fastag_logistics : 2500.00);

        return round($exShowroom + $rto + $insurance + $fastag, 2);
    }

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

    /**
     * Relationship: Variant has many price revision logs
     */
    public function priceLogs()
    {
        return $this->hasMany(VehiclePriceLog::class, 'variant_id')->latest();
    }
}
