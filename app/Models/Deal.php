<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'deal_number',
        'lead_id',
        'quotation_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_city',
        'customer_state',
        'customer_address',
        'vehicle_segment',
        'brand_id',
        'brand_name',
        'model_variant',
        'color',
        'vin_chassis_number',
        'engine_number',
        'registration_number',
        'total_amount',
        'discount_amount',
        'net_amount',
        'total_paid',
        'balance_due',
        'payment_status',
        'deal_status',
        'booking_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'sales_executive_id',
        'sales_executive_name',
        'created_by',
        'payment_terms',
        'delivery_terms',
        'notes',
        'cancellation_reason',
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'expected_delivery_date' => 'date:Y-m-d',
        'actual_delivery_date' => 'date:Y-m-d',
        'total_amount' => 'float',
        'discount_amount' => 'float',
        'net_amount' => 'float',
        'total_paid' => 'float',
        'balance_due' => 'float',
    ];

    protected $appends = [
        'payment_percentage',
    ];

    /**
     * Accessor: Calculate payment completion percentage
     */
    public function getPaymentPercentageAttribute(): float
    {
        $net = (float) $this->net_amount;
        if ($net <= 0) {
            return 0.0;
        }

        $percentage = ((float) $this->total_paid / $net) * 100;
        return round(min(100.0, max(0.0, $percentage)), 2);
    }

    /**
     * Relationship: Deal belongs to a Lead
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Relationship: Deal belongs to an optional Quotation
     */
    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    /**
     * Relationship: Deal belongs to a Brand
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Relationship: Deal assigned to Sales Executive
     */
    public function salesExecutive()
    {
        return $this->belongsTo(User::class, 'sales_executive_id');
    }

    /**
     * Relationship: Deal created by a User
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Deal has many payment records
     */
    public function payments()
    {
        return $this->hasMany(DealPayment::class, 'deal_id')->orderBy('payment_date', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Relationship: Cleared payments only
     */
    public function clearedPayments()
    {
        return $this->hasMany(DealPayment::class, 'deal_id')->where('status', 'cleared');
    }

    /**
     * Relationship: Insurance records for this Deal
     */
    public function insurances()
    {
        return $this->hasMany(Insurance::class, 'deal_id')->latest('id');
    }

    /**
     * Recalculate financial totals and payment status based on cleared payments
     */
    public function recalculateFinancials(): void
    {
        // Calculate Net Payable Amount
        $totalAmount = (float) ($this->total_amount ?? 0);
        $discountAmount = (float) ($this->discount_amount ?? 0);
        $netAmount = max(0.0, $totalAmount - $discountAmount);

        // Sum only verified/cleared payment receipts
        $totalCleared = (float) $this->clearedPayments()->sum('amount');

        $balanceDue = max(0.0, $netAmount - $totalCleared);

        // Determine Payment Status
        if ($totalCleared <= 0) {
            $paymentStatus = 'unpaid';
        } elseif ($totalCleared >= $netAmount && $netAmount > 0) {
            $paymentStatus = $totalCleared > $netAmount ? 'overpaid' : 'paid';
        } else {
            $paymentStatus = 'partially_paid';
        }

        $this->net_amount = $netAmount;
        $this->total_paid = $totalCleared;
        $this->balance_due = $balanceDue;
        $this->payment_status = $paymentStatus;

        $this->saveQuietly();
    }

    /**
     * Generate unique sequential deal number (format: DEAL-YYYY-XXXX)
     */
    public static function generateDealNumber(): string
    {
        $year = Carbon::now()->format('Y');
        $prefix = "DEAL-{$year}-";

        $latest = self::where('deal_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($latest) {
            $lastNumberStr = str_replace($prefix, '', $latest->deal_number);
            $nextSeq = (int) $lastNumberStr + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
