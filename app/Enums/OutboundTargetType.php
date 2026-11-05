<?php

namespace App\Enums;

enum OutboundTargetType: string
{
    case Telegram = 'telegram';
    case Broker = 'broker';
}
