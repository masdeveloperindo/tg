<?php

namespace App\Models;

abstract class CreatedOnlyModel extends DomainModel
{
    public const UPDATED_AT = null;
}
