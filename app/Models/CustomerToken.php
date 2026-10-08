<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerToken extends Model
{
    use HasFactory;

    protected $table = 'customer_tokens';

    protected $fillable = [
        'token_number',
        'token_date',
        'deal_id',
        'lead_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'service_id',
        'rank_designation',
        'brand_name',
        'vehicle_name',
        'model_variant',
        'total_deal_amount',
        'total_paid_amount',
        'remaining_balance',
        'paid_percentage',
        'invoices_count',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'token_date' => 'date:Y-m-d',
        'total_deal_amount' => 'float',
        'total_paid_amount' => 'float',
        'remaining_balance' => 'float',
        'paid_percentage' => 'float',
        'invoices_count' => 'integer',
    ];

    /**
     * Relationship: Customer Token has many Installment Invoices
     */
    public function invoices()
    {
        return $this->hasMany(InstallmentInvoice::class, 'token_id')->oldest();
    }

    /**
     * Relationship: Belongs to Deal
     */
    public function deal()
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    /**
     * Relationship: Belongs to Lead
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Relationship: Created by User
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Recalculate ledger financial totals based on issued installment invoices
     */
    public function recalculateLedger(): void
    {
        $paidSum = (float) $this->invoices()->where('status', 'Active')->where('payment_status', 'Paid')->sum('net_payable');
        $totalDeal = (float) $this->total_deal_amount;

        $balance = max(0.0, $totalDeal - $paidSum);
        $percentage = $totalDeal > 0 ? round(min(100.0, ($paidSum / $totalDeal) * 100), 1) : 0.0;
        $invoicesCount = $this->invoices()->where('status', 'Active')->count();

        $status = 'Partial';
        if ($balance <= 0 && $totalDeal > 0) {
            $status = 'Settled';
        } elseif ($paidSum <= 0) {
            $status = 'Pending';
        }

        $this->total_paid_amount = $paidSum;
        $this->remaining_balance = $balance;
        $this->paid_percentage = $percentage;
        $this->invoices_count = $invoicesCount;
        $this->status = $status;

        $this->saveQuietly();
    }

    /**
     * Generate unique sequential token number (TKN-YYYY-XXXX)
     */
    public static function generateTokenNumber(): string
    {
        $year = date('Y');
        $prefix = "TKN-{$year}-";

        $latest = self::where('token_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($latest) {
            $seq = (int) str_replace($prefix, '', $latest->token_number) + 1;
        } else {
            $seq = 1042; // Matching baseline sequence
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
