<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class TeamPlanEntriesExport implements FromArray, WithTitle
{
    public function __construct(
        private array $rows,
    ) {}

    public function title(): string
    {
        return 'Plan Entries';
    }

    public function array(): array
    {
        $headers = ['Email', 'Date', 'Location', 'Note', 'Availability'];

        return array_merge([$headers], $this->rows);
    }
}
