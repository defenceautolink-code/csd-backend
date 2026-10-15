<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DealPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'deal_id',
        'lead_id',
        'payment_type',
        'amount',
        'payment_date',
        'payment_mode',
        'transaction_reference',
        'bank_name',
        'cheque_date',
        'cheque_status',
        'payment_proof_path',
        'received_by',
        'status',
        'notes',
        'rejection_reason',
        'recorded_by',
        'recorded_by_name',
        'verified_by',
        'verified_by_name',
        'verified_at',
        'invoice_no'
    ];

    protected $casts = [
        'payment_date' => 'date:Y-m-d',
        'cheque_date' => 'date:Y-m-d',
        'verified_at' => 'datetime',
        'amount' => 'float',
    ];

    protected $appends = [
        'reference',
        'utr',
        'bank',
        'invoice_no',
    ];

    public function getReferenceAttribute()
    {
        return $this->attributes['transaction_reference'] ?? null;
    }

    public function getUtrAttribute()
    {
        return $this->attributes['transaction_reference'] ?? null;
    }

    public function getBankAttribute()
    {
        return $this->attributes['bank_name'] ?? null;
    }

    public function getInvoiceNoAttribute()
    {
        return $this->attributes['invoice_no'] ?? null;
    }

    public function getInvoiceNumberAttribute()
    {
        return $this->attributes['invoice_no'] ?? null;
    }

    /**
     * Boot model events to automatically keep Deal financial totals in sync
     */
    protected static function booted()
    {
        static::saved(function (DealPayment $payment) {
            if ($payment->deal) {
                $payment->deal->recalculateFinancials();
            }
        });

        static::deleted(function (DealPayment $payment) {
            if ($payment->deal) {
                $payment->deal->recalculateFinancials();
            }
        });
    }

    /**
     * Relationship: Payment belongs to a Deal
     */
    public function deal()
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    /**
     * Relationship: Payment belongs to a Lead
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Relationship: User who recorded this payment
     */
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Relationship: Accountant / Manager who verified this payment
     */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Generate unique sequential payment receipt number (format: REC-YYYY-XXXX)
     */
    public static function generateReceiptNumber(): string
    {
        $year = Carbon::now()->format('Y');
        $prefix = "REC-{$year}-";

        $latest = self::where('receipt_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($latest) {
            $lastNumberStr = str_replace($prefix, '', $latest->receipt_number);
            $nextSeq = (int) $lastNumberStr + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate unique sequential payment invoice number (format: INV-YYYY-XXXX)
     */
    public static function generateInvoiceNumber(): string
    {
        $year = Carbon::now()->format('Y');
        $prefix = "INV-{$year}-";

        $latest = self::where('invoice_no', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($latest) {
            $lastNumberStr = str_replace($prefix, '', $latest->invoice_no);
            $nextSeq = (int) $lastNumberStr + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
