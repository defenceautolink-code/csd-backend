<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountApproval extends Model
{
    use HasFactory;

    protected $table = 'discount_approvals';

    protected $fillable = [
        'lead_id',
        'follow_up_id',
        'requested_by',
        'action_by',
        'discount_amount',
        'proposed_price',
        'reason',
        'status',
        'remarks',
        'action_at',
    ];

    protected $casts = [
        'discount_amount' => 'float',
        'proposed_price' => 'float',
        'action_at' => 'datetime',
    ];

    /**
     * Relationship: Linked Lead
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Relationship: Associated Follow-up
     */
    public function followUp()
    {
        return $this->belongsTo(LeadFollowUp::class, 'follow_up_id');
    }

    /**
     * Relationship: User (Sales Executive / Receptionist) who requested the discount
     */
    public function requestedByUser()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Relationship: User (Sales Manager / Super Admin) who approved or rejected the discount
     */
    public function actionByUser()
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    /**
     * Scope: Only pending discount requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Only approved discount requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Only rejected discount requests
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
