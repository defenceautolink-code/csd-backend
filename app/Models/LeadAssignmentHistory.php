<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadAssignmentHistory extends Model
{
    use HasFactory;

    protected $table = 'lead_assignment_histories';

    protected $fillable = [
        'lead_id',
        'assign_to',
        'assign_by',
        'remarks',
    ];

    /**
     * Always eager load relational users with the history
     */
    protected $with = ['assignedToUser', 'assignedByUser'];

    /**
     * Computed dynamic attributes from ORM relations
     */
    protected $appends = ['assign_to_name', 'assign_by_name'];

    /**
     * Relationship: History belongs to a Lead
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Relationship: History assigned to a User
     */
    public function assignedToUser()
    {
        return $this->belongsTo(User::class, 'assign_to');
    }

    /**
     * Relationship: History assigned by a User
     */
    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assign_by');
    }

    /**
     * Accessor: Compute assign_to_name via User ORM relation
     */
    public function getAssignToNameAttribute()
    {
        if ($this->assignedToUser) {
            return $this->assignedToUser->role 
                ? "{$this->assignedToUser->name} ({$this->assignedToUser->role})" 
                : $this->assignedToUser->name;
        }
        return 'Unassigned';
    }

    /**
     * Accessor: Compute assign_by_name via User ORM relation
     */
    public function getAssignByNameAttribute()
    {
        if ($this->assignedByUser) {
            return $this->assignedByUser->role 
                ? "{$this->assignedByUser->name} ({$this->assignedByUser->role})" 
                : $this->assignedByUser->name;
        }
        return 'System Admin';
    }
}
