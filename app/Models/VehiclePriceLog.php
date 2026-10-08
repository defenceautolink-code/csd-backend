<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehiclePriceLog extends Model
{
    use HasFactory;

    protected $table = 'vehicle_price_logs';

    protected $fillable = [
        'variant_id',
        'model_id',
        'brand_id',
        'model_variant_name',
        'brand_name',
        'previous_ex_showroom',
        'revised_ex_showroom',
        'net_difference',
        'rto_road_tax',
        'insurance',
        'fastag_logistics',
        'previous_on_road',
        'revised_on_road',
        'updated_by_id',
        'updated_by_name',
        'revision_date',
        'status',
    ];

    protected $casts = [
        'previous_ex_showroom' => 'float',
        'revised_ex_showroom' => 'float',
        'net_difference' => 'float',
        'rto_road_tax' => 'float',
        'insurance' => 'float',
        'fastag_logistics' => 'float',
        'previous_on_road' => 'float',
        'revised_on_road' => 'float',
        'revision_date' => 'date:Y-m-d',
    ];

    /**
     * Relationship: Price Log belongs to a Variant
     */
    public function variant()
    {
        return $this->belongsTo(VehicleVariant::class, 'variant_id');
    }

    /**
     * Relationship: Price Log belongs to a Model
     */
    public function model()
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    /**
     * Relationship: Price Log belongs to a Brand
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Relationship: User who performed the price revision
     */
    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
