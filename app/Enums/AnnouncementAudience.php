<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    case All = 'all';
    case Clients = 'clients';
    case IbClients = 'ib_clients';
}
