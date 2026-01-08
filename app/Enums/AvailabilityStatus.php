<?php

namespace App\Enums;

enum AvailabilityStatus: int
{
    case NOT_AVAILABLE = 0;
    case REMOTE = 1;
    case ONSITE = 2;

    public function label(): string
    {
        return match ($this) {
            self::NOT_AVAILABLE => 'Not Available',
            self::REMOTE => 'Remote',
            self::ONSITE => 'Onsite',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::NOT_AVAILABLE => 'zinc',
            self::REMOTE => 'sky',
            self::ONSITE => 'emerald',
        };
    }

    public function isAvailable(): bool
    {
        return $this->value > 0;
    }

    public function code(): string
    {
        return match ($this) {
            self::NOT_AVAILABLE => 'N',
            self::REMOTE => 'R',
            self::ONSITE => 'O',
        };
    }
}
