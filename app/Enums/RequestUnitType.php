<?php

namespace App\Enums;

enum RequestUnitType: string
{
    case Apartment = 'apartment';
    case Studio = 'studio';
    case Villa = 'villa';
    case Other = 'other';
}
