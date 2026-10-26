<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Closed = 'closed';
}
