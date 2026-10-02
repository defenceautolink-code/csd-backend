<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'profile_photo',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relationship: User has created many quotations
     */
    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'created_by');
    }

    /**
     * Relationship: Deals created by User
     */
    public function deals()
    {
        return $this->hasMany(Deal::class, 'created_by');
    }

    /**
     * Relationship: Deals assigned to Sales Executive
     */
    public function assignedDeals()
    {
        return $this->hasMany(Deal::class, 'sales_executive_id');
    }

    /**
     * Relationship: Payment receipts recorded by this User
     */
    public function recordedPayments()
    {
        return $this->hasMany(DealPayment::class, 'recorded_by');
    }

    /**
     * Relationship: Payment receipts verified by this User (Accountant/Manager)
     */
    public function verifiedPayments()
    {
        return $this->hasMany(DealPayment::class, 'verified_by');
    }
}

