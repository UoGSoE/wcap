<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class ManagerReportExport implements WithMultipleSheets
{
    public function __construct(
        private array $payload,
    ) {}

    public function sheets(): array
    {
        return [
            new TeamReportSheet($this->payload['days'], $this->payload['teamRows']),
            new LocationReportSheet($this->payload['locationDays']),
            new CoverageReportSheet($this->payload['days'], $this->payload['coverageMatrix']),
            new ServiceAvailabilitySheet($this->payload['days'], $this->payload['serviceAvailabilityMatrix']),
        ];
    }
}

class TeamReportSheet implements FromArray, WithTitle
{
    public function __construct(
        private array $days,
        private array $teamRows,
    ) {}

    public function title(): string
    {
        return 'Team';
    }

    public function array(): array
    {
        $headers = array_merge(
            ['Team Member'],
            array_map(
                fn ($day) => $day['date']->format('D jS'),
                $this->days,
            ),
        );

        $rows = [$headers];

        foreach ($this->teamRows as $row) {
            $rowData = [$row['name']];

            foreach ($row['days'] as $dayData) {
                $rowData[] = $this->formatDay($dayData);
            }

            $rows[] = $rowData;
        }

        return $rows;
    }

    private function formatDay(array $dayData): string
    {
        if ($dayData['state'] === 'planned') {
            $location = $dayData['location_short'] ?? '';
            $note = $dayData['note'] ?? '';

            return trim(
                implode(' - ', array_filter([$location, $note])),
            );
        }

        if ($dayData['state'] === 'away') {
            return 'Away';
        }

        return 'Missing';
    }
}

class LocationReportSheet implements FromArray, WithTitle
{
    public function __construct(
        private array $locationDays,
    ) {}

    public function title(): string
    {
        return 'Locations';
    }

    public function array(): array
    {
        $rows = [
            ['Date', 'Location', 'Members'],
        ];

        foreach ($this->locationDays as $dayData) {
            foreach ($dayData['locations'] as $location) {
                $rows[] = [
                    $dayData['date']->format('D, j M Y'),
                    $location['label'],
                    $this->formatMembers($location['members']),
                ];
            }
        }

        return $rows;
    }

    private function formatMembers(array $members): string
    {
        if (empty($members)) {
            return 'None';
        }

        $lines = array_map(
            function (array $member): string {
                $note = trim($member['note'] ?? '');

                return $note !== ''
                    ? "{$member['name']} ({$note})"
                    : $member['name'];
            },
            $members,
        );

        return implode("\n", $lines);
    }
}

class CoverageReportSheet implements FromArray, WithTitle
{
    public function __construct(
        private array $days,
        private array $coverageMatrix,
    ) {}

    public function title(): string
    {
        return 'Coverage';
    }

    public function array(): array
    {
        $headers = array_merge(
            ['Location'],
            array_map(
                fn ($day) => $day['date']->format('D jS'),
                $this->days,
            ),
        );

        $rows = [$headers];

        foreach ($this->coverageMatrix as $row) {
            $rowData = [$row['label']];

            foreach ($row['entries'] as $entry) {
                $rowData[] = $entry['count'] > 0 ? $entry['count'] : '';
            }

            $rows[] = $rowData;
        }

        return $rows;
    }
}

class ServiceAvailabilitySheet implements FromArray, WithTitle
{
    public function __construct(
        private array $days,
        private array $serviceAvailabilityMatrix,
    ) {}

    public function title(): string
    {
        return 'Service Availability';
    }

    public function array(): array
    {
        $headers = array_merge(
            ['Service'],
            array_map(
                fn ($day) => $day['date']->format('D jS'),
                $this->days,
            ),
        );

        $rows = [$headers];

        foreach ($this->serviceAvailabilityMatrix as $row) {
            $rowData = [$row['label']];

            foreach ($row['entries'] as $entry) {
                if ($entry['count'] > 0) {
                    $rowData[] = $entry['count'];

                    continue;
                }

                $rowData[] = $entry['manager_only'] ? 'Manager only' : '';
            }

            $rows[] = $rowData;
        }

        return $rows;
    }
}
