<?php

namespace App\Enums;

enum AccessMethod: string
{
    case Password = 'password';
    case Google = 'google';
}
