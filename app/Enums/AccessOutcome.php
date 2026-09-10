<?php

namespace App\Enums;

enum AccessOutcome: string
{
    case Success = 'success';
    case Rejected = 'rejected';
}
