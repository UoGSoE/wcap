<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class OccupancyReportExport implements WithMultipleSheets
{
    public function __construct(
        private array $payload,
    ) {}

    public function sheets(): array
    {
        return [
            new OccupancyHeatmapSheet(
                $this->payload['days'],
                $this->payload['periodMatrix'],
                $this->payload['aggregation'],
            ),
            new OccupancyStatsSheet($this->payload['summaryStats']),
        ];
    }
}

class OccupancyHeatmapSheet implements FromArray, WithTitle
{
    public function __construct(
        private array $days,
        private array $periodMatrix,
        private string $aggregation,
    ) {}

    public function title(): string
    {
        return $this->aggregation === 'weekly' ? 'Weekly Heatmap' : 'Daily Heatmap';
    }

    public function array(): array
    {
        $headers = ['Location', 'Base Capacity'];

        foreach ($this->days as $day) {
            if ($this->aggregation === 'weekly') {
                $headers[] = 'W/C '.$day['date']->format('j M');
            } else {
                $headers[] = $day['date']->format('D j/n');
            }
        }

        $rows = [$headers];

        foreach ($this->periodMatrix as $row) {
            $rowData = [
                $row['location_name'],
                $row['base_capacity'],
            ];

            foreach ($row['days'] as $dayData) {
                $rowData[] = $dayData['total_present'] > 0 ? $dayData['total_present'] : '';
            }

            $rows[] = $rowData;
        }

        return $rows;
    }
}

class OccupancyStatsSheet implements FromArray, WithTitle
{
    public function __construct(
        private array $summaryStats,
    ) {}

    public function title(): string
    {
        return 'Statistics';
    }

    public function array(): array
    {
        $rows = [
            ['Location', 'Base Capacity', 'Mean', 'Median', 'Peak', 'Peak Date'],
        ];

        foreach ($this->summaryStats as $stat) {
            $rows[] = [
                $stat['location_name'],
                $stat['base_capacity'],
                $stat['mean_occupancy'],
                $stat['median_occupancy'],
                $stat['peak_occupancy'],
                $stat['peak_date']?->format('D j/n/Y') ?? '-',
            ];
        }

        return $rows;
    }
}
