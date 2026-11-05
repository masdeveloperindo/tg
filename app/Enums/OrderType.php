<?php

namespace App\Enums;

enum OrderType: string
{
    case New = 'new';
    case Renewal = 'renewal';
}
