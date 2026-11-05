<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Initiated = 'initiated';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
