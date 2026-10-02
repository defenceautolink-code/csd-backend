<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'Super Admin';
    case SALES_MANAGER = 'Sales Manager';
    case SALES_EXECUTIVE = 'Sales Executive';
    case RECEPTIONIST = 'Receptionist';
    case ACCOUNTANT = 'Accountant';
    case CUSTOMER = 'Customer';

    /**
     * Get all enum string values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
