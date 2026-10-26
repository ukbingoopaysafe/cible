<?php

namespace App\Enums;

enum FloorPreference: string
{
    case Ground = 'ground';
    case Low = 'low';
    case Middle = 'middle';
    case High = 'high';
    case Top = 'top';
    case NoPreference = 'no_preference';
}
