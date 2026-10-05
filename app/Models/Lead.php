<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'city',
        'state',
        'birth_date',
        'anniversary_date',
        'last_birthday_wished_year',
        'last_anniversary_wished_year',
        'vehicle_segment',
        'brand_id',
        'brand_name',
        'model_id',
        'variant_id',
        'model_variant',
        'priority',
        'purchase_timeline',
        'source_id',
        'source_name',
        'status_id',
        'status_name',
        'assigned_to',
        'assigned_user_name',
    ];

    /**
     * Cast attributes to native types
     */
    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'anniversary_date' => 'date:Y-m-d',
        'last_birthday_wished_year' => 'integer',
        'last_anniversary_wished_year' => 'integer',
    ];

    /**
     * Computed dynamic attributes appended to JSON serialization
     */
    protected $appends = [
        'assigned_to_display',
        'assigned_by_display',
        'assigned_by_user',
        'is_birthday_today',
        'is_anniversary_today',
    ];

    /**
     * Accessor: Check if today is the lead's birthday (matching MM-DD)
     */
    public function getIsBirthdayTodayAttribute(): bool
    {
        if (!$this->birth_date) return false;
        $dob = is_string($this->birth_date) ? \Carbon\Carbon::parse($this->birth_date) : $this->birth_date;
        return $dob->format('m-d') === now()->format('m-d');
    }

    /**
     * Accessor: Check if today is the lead's anniversary (matching MM-DD)
     */
    public function getIsAnniversaryTodayAttribute(): bool
    {
        if (!$this->anniversary_date) return false;
        $anni = is_string($this->anniversary_date) ? \Carbon\Carbon::parse($this->anniversary_date) : $this->anniversary_date;
        return $anni->format('m-d') === now()->format('m-d');
    }

    /**
     * Relationship: Lead belongs to a Brand
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Relationship: Lead belongs to a Vehicle Model
     */
    public function vehicleModel()
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    /**
     * Relationship alias: model
     */
    public function model()
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    /**
     * Relationship: Lead belongs to a Vehicle Variant
     */
    public function variant()
    {
        return $this->belongsTo(VehicleVariant::class, 'variant_id');
    }

    /**
     * Relationship alias: vehicleVariant
     */
    public function vehicleVariant()
    {
        return $this->belongsTo(VehicleVariant::class, 'variant_id');
    }

    /**
     * Relationship: Lead belongs to a Lead Source
     */
    public function source()
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    /**
     * Relationship: Lead belongs to a Lead Status
     */
    public function status()
    {
        return $this->belongsTo(LeadStatus::class, 'status_id');
    }

    /**
     * Relationship: Lead assigned to a User
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Relationship alias: assigned_user
     */
    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Relationship: Lead has many assignment histories
     */
    public function assignmentHistories()
    {
        return $this->hasMany(LeadAssignmentHistory::class, 'lead_id')->latest();
    }

    /**
     * Relationship: Latest assignment history record
     */
    public function latestAssignment()
    {
        return $this->hasOne(LeadAssignmentHistory::class, 'lead_id')->latestOfMany();
    }

    /**
     * Relationship: Lead has many follow-ups
     */
    public function followUps()
    {
        return $this->hasMany(LeadFollowUp::class, 'lead_id')->latest();
    }

    /**
     * Relationship: Latest follow-up record
     */
    public function latestFollowUp()
    {
        return $this->hasOne(LeadFollowUp::class, 'lead_id')->latestOfMany();
    }

    /**
     * Relationship: Lead has many quotations
     */
    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'lead_id')->latest();
    }

    /**
     * Relationship: Latest quotation record
     */
    public function latestQuotation()
    {
        return $this->hasOne(Quotation::class, 'lead_id')->latestOfMany();
    }

    /**
     * Accessor: Formatted assigned_to user or '-' if unassigned
     */
    public function getAssignedToDisplayAttribute()
    {
        if ($this->assignedUser) {
            return $this->assignedUser->role 
                ? "{$this->assignedUser->name} ({$this->assignedUser->role})" 
                : $this->assignedUser->name;
        }

        if (!empty($this->assigned_user_name) && trim($this->assigned_user_name) !== '') {
            return $this->assigned_user_name;
        }

        return '-';
    }

    /**
     * Accessor: Formatted assigned_by user or '-' if unassigned
     */
    public function getAssignedByDisplayAttribute()
    {
        if ($this->latestAssignment && $this->latestAssignment->assignedByUser) {
            $user = $this->latestAssignment->assignedByUser;
            return $user->role 
                ? "{$user->name} ({$user->role})" 
                : $user->name;
        }

        return '-';
    }

    /**
     * Accessor: Assigned by User relation object or null
     */
    public function getAssignedByUserAttribute()
    {
        return $this->latestAssignment?->assignedByUser;
    }

    /**
     * Relationship: Lead has many deals
     */
    public function deals()
    {
        return $this->hasMany(Deal::class, 'lead_id')->latest();
    }

    /**
     * Relationship: Latest deal record
     */
    public function latestDeal()
    {
        return $this->hasOne(Deal::class, 'lead_id')->latestOfMany();
    }

    /**
     * Relationship: Lead has many payments across deals
     */
    public function payments()
    {
        return $this->hasMany(DealPayment::class, 'lead_id')->latest();
    }
}

