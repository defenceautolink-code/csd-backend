<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadFollowUp extends Model
{
    use HasFactory;

    protected $table = 'lead_follow_ups';

    protected $fillable = [
        'lead_id',
        'user_id',
        'follow_up_date',
        'follow_up_time',
        'type',
        'notes',
        'next_follow_up_date',
        'next_follow_up_time',
        'status',
    ];

    /**
     * Computed dynamic attributes appended to JSON serialization
     */
    protected $appends = [
        'user_name',
    ];

    /**
     * Relationship: Follow-up belongs to a Lead
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Relationship: Follow-up created by a User (Sales Executive)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Accessor: Formatted creator user name
     */
    public function getUserNameAttribute()
    {
        if ($this->user) {
            return $this->user->role 
                ? "{$this->user->name} ({$this->user->role})" 
                : $this->user->name;
        }

        return 'Sales Executive';
    }
}
