<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceRenewalHistory extends Model
{
    protected $fillable = [
        'insurance_id',
        'deal_id',
        'policy_number',
        'insurance_company',
        'insurance_type',
        'policy_start_date',
        'policy_expiry_date',
        'premium_amount',
        'reminder_days_before',
        'reminder_date',
        'renewal_status',
        'renewed_at',
        'notes',
    ];

    protected $casts = [
        'policy_start_date' => 'date:Y-m-d',
        'policy_expiry_date' => 'date:Y-m-d',
        'reminder_date' => 'date:Y-m-d',
        'renewed_at' => 'datetime',
        'premium_amount' => 'float',
        'reminder_days_before' => 'integer',
    ];

    public function insurance()
    {
        return $this->belongsTo(Insurance::class, 'insurance_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }
}
