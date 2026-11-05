<?php

namespace App\Enums;

enum LicenseFlagStatus: string
{
    case New = 'new';
    case Reviewed = 'reviewed';
    case Ignored = 'ignored';
    case Suspended = 'suspended';
}
