<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'quotation_number',
        'quotation_date',
        'valid_until',
        'customer_name',
        'company_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'subject',
        'description',
        'subtotal',
        'discount',
        'tax',
        'grand_total',
        'payment_terms',
        'delivery_terms',
        'notes',
        'status',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'quotation_date' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
        'sent_at' => 'datetime',
        'subtotal' => 'float',
        'discount' => 'float',
        'tax' => 'float',
        'grand_total' => 'float',
    ];

    /**
     * Relationship: Quotation belongs to a Lead
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Relationship: Quotation has many items
     */
    public function items()
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }

    /**
     * Relationship: Quotation created by a User
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Quotation converted to Deals
     */
    public function deals()
    {
        return $this->hasMany(Deal::class, 'quotation_id');
    }

    /**
     * Relationship: Latest Deal
     */
    public function latestDeal()
    {
        return $this->hasOne(Deal::class, 'quotation_id')->latestOfMany();
    }

    /**
     * Generate unique sequential quotation number in format QT-YYYY-XXXX
     */
    public static function generateQuotationNumber(): string
    {
        $year = Carbon::now()->format('Y');
        $prefix = "QT-{$year}-";

        $latest = self::where('quotation_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($latest) {
            $lastNumberStr = str_replace($prefix, '', $latest->quotation_number);
            $nextSeq = (int) $lastNumberStr + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
