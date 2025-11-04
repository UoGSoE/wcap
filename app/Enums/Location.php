<?php

namespace App\Enums;

enum Location: string
{
    case HOME = 'home';
    case JWS = 'jws';
    case JWN = 'jwn';
    case RANKINE = 'rankine';
    case BO = 'boyd-orr';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HOME => 'Home',
            self::JWS => 'JWS',
            self::JWN => 'JWN',
            self::RANKINE => 'Rankine',
            self::BO => 'Boyd-Orr',
            self::OTHER => 'Other',
        };
    }
}
