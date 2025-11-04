<?php

namespace App\Enums;

enum Category: string
{
    case SUPPORT = 'support';
    case PROJECT = 'project';
    case ADMIN = 'admin';
    case LEAVE = 'leave';

    public function label(): string
    {
        return match ($this) {
            self::SUPPORT => 'Support',
            self::PROJECT => 'Project',
            self::ADMIN => 'Admin',
            self::LEAVE => 'Leave',
        };
    }
}
