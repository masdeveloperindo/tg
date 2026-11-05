<?php

namespace App\Enums;

enum LicenseStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
}
