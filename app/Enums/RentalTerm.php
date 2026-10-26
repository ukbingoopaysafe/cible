<?php

namespace App\Enums;

enum RentalTerm: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';
}
