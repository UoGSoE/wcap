<div>
    <div class="mb-6">
        <flux:heading size="xl">Office Occupancy Report</flux:heading>
        <flux:subheading>Track physical office usage across locations</flux:subheading>
    </div>

    <flux:tab.group>
        <flux:tabs wire:model.live="tab">
            <flux:tab name="today">Today</flux:tab>
            <flux:tab name="period">This Period</flux:tab>
            <flux:tab name="summary">Summary</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="today">
            <flux:subheading class="mb-2">{{ $snapshotDate->format('l, F jS Y') }}</flux:subheading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">
                Shows who is physically present at each location today. Total present shown with visitor count in badge.
            </flux:text>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($daySnapshot as $location)
                    <div class="p-4 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <flux:heading size="lg">{{ $location['location_name'] }}</flux:heading>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                    Base capacity: {{ $location['base_capacity'] }}
                                </flux:text>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:text variant="strong" class="text-2xl">{{ $location['total_present'] }}</flux:text>
                                    @if($location['visitor_count'] > 0)
                                        <flux:badge icon="user-circle" size="sm" color="sky" inset="top bottom">{{ $location['visitor_count'] }}</flux:badge>
                                    @endif
                                </div>
                                <flux:text class="text-sm {{ $location['utilization_pct'] > 100 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-zinc-500' }}">
                                    {{ $location['utilization_pct'] }}% utilization
                                </flux:text>
                            </div>
                        </div>

                        @if ($location['home_users']->isNotEmpty() || $location['visitor_users']->isNotEmpty())
                            <div class="border-t border-zinc-200 dark:border-zinc-700 pt-3 mt-3">
                                @if ($location['home_users']->isNotEmpty())
                                    <flux:text class="text-xs text-zinc-500 uppercase tracking-wide mb-1">Home Staff</flux:text>
                                    <div class="flex flex-wrap gap-1 mb-2">
                                        @foreach ($location['home_users'] as $user)
                                            <flux:badge size="sm" inset="top bottom">{{ $user->surname }}</flux:badge>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($location['visitor_users']->isNotEmpty())
                                    <flux:text class="text-xs text-zinc-500 uppercase tracking-wide mb-1">Visitors</flux:text>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($location['visitor_users'] as $user)
                                            <flux:badge size="sm" color="sky" inset="top bottom">{{ $user->surname }}</flux:badge>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @else
                            <flux:text class="text-sm text-zinc-500 italic">No one present</flux:text>
                        @endif
                    </div>
                @endforeach
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="period">
            <flux:subheading class="mb-2">Two-week occupancy overview</flux:subheading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">
                Shows occupancy for each location over the planning period. Format: home staff (+visitors). Colour intensity indicates utilization.
            </flux:text>

            <div class="overflow-x-auto">
                <div class="grid gap-1 p-0.5 rounded-lg min-w-max" style="grid-template-columns: 150px repeat({{ count($days) }}, minmax(70px, 1fr));">
                    {{-- Header row --}}
                    <div class="p-2">
                        <flux:text variant="strong">Location</flux:text>
                    </div>
                    @foreach ($days as $day)
                        <div class="p-2 text-center">
                            <flux:text variant="strong" class="text-xs">{{ $day['date']->format('D') }}</flux:text>
                            <flux:text class="text-xs block">{{ $day['date']->format('j/n') }}</flux:text>
                        </div>
                    @endforeach

                    {{-- Location rows --}}
                    @foreach ($periodMatrix as $row)
                        <div class="p-2 flex items-center">
                            <flux:tooltip content="Base capacity: {{ $row['base_capacity'] }}">
                                <flux:text variant="strong" class="cursor-help">
                                    {{ $row['short_label'] }} ({{ $row['base_capacity'] }})
                                </flux:text>
                            </flux:tooltip>
                        </div>
                        @foreach ($row['days'] as $dayData)
                            @php
                                $cellClass = match(true) {
                                    $dayData['utilization_pct'] >= 80 => 'bg-emerald-200 dark:bg-emerald-800',
                                    $dayData['utilization_pct'] >= 50 => 'bg-emerald-100 dark:bg-emerald-900',
                                    $dayData['utilization_pct'] > 0 => 'bg-amber-100 dark:bg-amber-900/50',
                                    default => 'bg-zinc-100 dark:bg-zinc-800',
                                };
                            @endphp
                            <div class="p-2 text-center text-sm {{ $cellClass }} rounded">
                                @if ($dayData['total_present'] > 0)
                                    <span class="font-medium">{{ $dayData['home_count'] }}</span>@if($dayData['visitor_count'] > 0)<span class="text-zinc-500 text-xs"> +{{ $dayData['visitor_count'] }}</span>@endif
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="mt-4 flex gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-emerald-200 dark:bg-emerald-800"></div>
                    <flux:text>80%+ utilization</flux:text>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-emerald-100 dark:bg-emerald-900"></div>
                    <flux:text>50-79% utilization</flux:text>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-amber-100 dark:bg-amber-900/50"></div>
                    <flux:text>1-49% utilization</flux:text>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-zinc-100 dark:bg-zinc-800"></div>
                    <flux:text>Empty</flux:text>
                </div>
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="summary">
            <flux:subheading class="mb-2">Occupancy statistics</flux:subheading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mb-6">
                Summary statistics across the two-week planning period. Use these figures to demonstrate space utilization.
            </flux:text>

            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Location</flux:table.column>
                        <flux:table.column align="center">Base Capacity</flux:table.column>
                        <flux:table.column align="center">Mean</flux:table.column>
                        <flux:table.column align="center">Median</flux:table.column>
                        <flux:table.column align="center">Peak</flux:table.column>
                        <flux:table.column>Peak Date</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($summaryStats as $stat)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">
                                    {{ $stat['location_name'] }}
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    {{ $stat['base_capacity'] }}
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    {{ $stat['mean_occupancy'] }}
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    {{ $stat['median_occupancy'] }}
                                </flux:table.cell>
                                <flux:table.cell align="center">
                                    <flux:badge
                                        size="sm"
                                        :color="$stat['peak_occupancy'] > $stat['base_capacity'] ? 'red' : ($stat['peak_occupancy'] >= $stat['base_capacity'] ? 'emerald' : 'zinc')"
                                        inset="top bottom"
                                    >
                                        {{ $stat['peak_occupancy'] }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($stat['peak_date'])
                                        {{ $stat['peak_date']->format('D j/n') }}
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <flux:callout icon="information-circle" class="mt-6">
                <flux:callout.heading>Reading these statistics</flux:callout.heading>
                <flux:callout.text>
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>Base Capacity</strong> - Number of staff assigned to this location as their default desk</li>
                        <li><strong>Mean</strong> - Average daily occupancy over the period</li>
                        <li><strong>Median</strong> - Middle value of daily occupancy (less affected by outliers)</li>
                        <li><strong>Peak</strong> - Highest occupancy recorded (may exceed base due to visitors)</li>
                    </ul>
                </flux:callout.text>
            </flux:callout>
        </flux:tab.panel>
    </flux:tab.group>
</div>
