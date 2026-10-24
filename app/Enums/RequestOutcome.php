<?php

namespace App\Enums;

enum RequestOutcome: string
{
    case Successful = 'successful';
    case Unsuccessful = 'unsuccessful';
}
