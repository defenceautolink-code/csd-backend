<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expenses';

    protected $fillable = [
        'expense_date',
        'category_id',
        'title',
        'description',
        'amount',
        'payment_method',
        'reference_no',
        'attachment',
        'status',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date:Y-m-d',
        'amount' => 'float',
    ];

    /**
     * Relationship: Expense belongs to a Category
     */
    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * Relationship: Expense created by a User
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
