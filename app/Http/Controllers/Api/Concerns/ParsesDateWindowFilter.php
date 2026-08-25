<?php

namespace App\Http\Controllers\Api\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;

trait ParsesDateWindowFilter
{
    private int $maxWindowDays = 62;

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseDateWindow(Request $request): array
    {
        $from = $request->input('filter.from');
        $to = $request->input('filter.to');

        if ($from === null && $to === null) {
            return [null, null];
        }

        abort_if(
            $from === null || $to === null,
            400,
            'filter[from] and filter[to] must be provided together.',
        );

        abort_if(
            ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to),
            400,
            'filter[from] and filter[to] must be ISO dates (YYYY-MM-DD).',
        );

        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);

        abort_if(
            $fromDate->gt($toDate),
            400,
            'filter[from] must be on or before filter[to].',
        );

        abort_if(
            $fromDate->diffInDays($toDate) > $this->maxWindowDays,
            400,
            'Date range is too large (max '.$this->maxWindowDays.' days).',
        );

        return [$from, $to];
    }
}
