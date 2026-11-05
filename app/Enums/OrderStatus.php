<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Waiting = 'waiting';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
