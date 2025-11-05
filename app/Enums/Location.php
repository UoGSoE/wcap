<?php

namespace App\Enums;

enum Location: string
{
    case JWS = 'jws';
    case JWN = 'jwn';
    case RANKINE = 'rankine';
    case BO = 'boyd-orr';
    case OTHER = 'other';
    case JOSEPH_BLACK = 'joseph-black';
    case ALWYN_WILLIAM = 'alwyn-william';
    case GILBERT_SCOTT = 'gilbert-scott';
    case KELVIN = 'kelvin';
    case MATHS = 'maths';

    public function label(): string
    {
        return match ($this) {
            self::JWS => 'JWS',
            self::JWN => 'JWN',
            self::RANKINE => 'Rankine',
            self::BO => 'Boyd-Orr',
            self::OTHER => 'Other',
            self::JOSEPH_BLACK => 'Joseph Black',
            self::ALWYN_WILLIAM => 'Alwyn William',
            self::GILBERT_SCOTT => 'Gilbert Scott',
            self::KELVIN => 'Kelvin',
            self::MATHS => 'Maths',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::JWS => 'JWS',
            self::JWN => 'JWN',
            self::RANKINE => 'Rank',
            self::BO => 'BO',
            self::OTHER => 'Other',
            self::JOSEPH_BLACK => 'JB',
            self::ALWYN_WILLIAM => 'AW',
            self::GILBERT_SCOTT => 'GS',
            self::KELVIN => 'Kelv',
            self::MATHS => 'Maths',
        };
    }
}
