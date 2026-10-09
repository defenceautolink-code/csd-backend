<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Insurance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'insurances';

    protected $fillable = [
        'policy_number',
        'insurance_company',
        'insurance_type',
        'policy_type',
        'deal_id',
        'lead_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'vehicle_name',
        'registration_number',
        'vin_chassis_number',
        'engine_number',
        'premium_amount',
        'idv_amount',
        'delivery_date',
        'start_date',
        'expiry_date',
        'next_insurance_date',
        'status',
        'reminder_sent',
        'notes',
        'created_by',
        'sales_executive_id',
        'sales_executive_name',
    ];

    protected $casts = [
        'delivery_date' => 'date:Y-m-d',
        'start_date' => 'date:Y-m-d',
        'expiry_date' => 'date:Y-m-d',
        'next_insurance_date' => 'date:Y-m-d',
        'premium_amount' => 'float',
        'idv_amount' => 'float',
        'reminder_sent' => 'boolean',
    ];

    protected $appends = [
        'days_until_due',
        'computed_status',
    ];

    /**
     * Accessor: Compute dynamic days remaining until next insurance date
     */
    public function getDaysUntilDueAttribute(): int
    {
        if (!$this->next_insurance_date) {
            return 0;
        }

        $today = Carbon::today();
        $nextDate = Carbon::parse($this->next_insurance_date)->startOfDay();

        return (int) $today->diffInDays($nextDate, false);
    }

    /**
     * Accessor: Dynamically determine status based on next_insurance_date
     * Returns: active, expiring_soon (<= 30 days), expired (passed), or renewed
     */
    public function getComputedStatusAttribute(): string
    {
        if ($this->status === 'renewed') {
            return 'renewed';
        }

        $days = $this->days_until_due;

        if ($days < 0) {
            return 'expired';
        } elseif ($days <= 30) {
            return 'expiring_soon';
        }

        return 'active';
    }

    /**
     * Relationship: Insurance belongs to a Deal
     */
    public function deal()
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    /**
     * Relationship: Insurance belongs to a Lead
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Relationship: Staff member who created this insurance record
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Sales Executive assigned
     */
    public function salesExecutive()
    {
        return $this->belongsTo(User::class, 'sales_executive_id');
    }
}
