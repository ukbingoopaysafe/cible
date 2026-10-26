<?php

namespace App\Enums;

enum ResaleRequestStage: string
{
    case Intake = 'intake';
    case Qualification = 'qualification';
    case UnitSearch = 'unit_search';
    case Recommendation = 'recommendation';
    case Viewing = 'viewing';
    case Negotiation = 'negotiation';
}
