<?php

namespace App\Enums;

enum FinishingLevel: string
{
    case Unfinished = 'unfinished';
    case HalfFinished = 'half_finished';
    case SuperLux = 'super_lux';
    case UltraSuperLux = 'ultra_super_lux';
    case Hotel = 'hotel';
    case Smart = 'smart';
    case Other = 'other';
}
