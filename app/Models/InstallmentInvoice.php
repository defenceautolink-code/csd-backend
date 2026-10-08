<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstallmentInvoice extends Model
{
    use HasFactory;

    protected $table = 'installment_invoices';

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'token_id',
        'deal_id',
        'lead_id',
        'customer_name',
        'customer_phone',
        'service_id',
        'booked_vehicle',
        'bill_type',
        'invoice_amount',
        'tax_amount',
        'net_payable',
        'payment_mode',
        'transaction_reference',
        'payment_status',
        'status',
        'pdf_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'invoice_amount' => 'float',
        'tax_amount' => 'float',
        'net_payable' => 'float',
    ];

    /**
     * Relationship: Invoice belongs to Customer Token
     */
    public function token()
    {
        return $this->belongsTo(CustomerToken::class, 'token_id');
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
     * Generate unique sequential invoice number (DAL-YYYY/MM/XXXX)
     */
    public static function generateInvoiceNumber(): string
    {
        $yearMonth = date('Y/m');
        $prefix = "DAL-{$yearMonth}/";

        $latest = self::where('invoice_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($latest) {
            $seq = (int) str_replace($prefix, '', $latest->invoice_number) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
