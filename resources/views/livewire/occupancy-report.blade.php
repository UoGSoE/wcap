<div>
    <div class="mb-6 flex justify-between items-start">
        <div>
            <flux:heading size="xl">Office Occupancy Report</flux:heading>
            <flux:subheading>Track physical office usage across locations</flux:subheading>
        </div>
        <flux:dropdown>
            <flux:button variant="primary" icon="arrow-down-tray" icon:trailing="chevron-down">Export</flux:button>
            <flux:menu>
                <flux:menu.item wire:click="exportCurrent" icon="document-arrow-down">
                    Export current view
                </flux:menu.item>
                <flux:menu.item wire:click="exportDetailed" icon="table-cells">
                    Export detailed (daily)
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>

    <flux:tab.group>
        <flux:tabs wire:model.live="tab">
            <flux:tab name="today">Date</flux:tab>
            <flux:tab name="period">Heatmap</flux:tab>
            <flux:tab name="summary">Stats</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="today">
            <div class="flex items-center gap-4 mb-6">
                <flux:date-picker wire:model.live="date" with-today />
                <flux:heading size="lg">{{ $snapshotDate->format('l, F jS Y') }}</flux:heading>
            </div>
            <flux:text size="sm" variant="subtle" class="mb-4">
                Shows who is physically present at each location. Total present shown with visitor count in badge.
            </flux:text>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($daySnapshot as $location)
                    <flux:card>
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <flux:heading size="lg">{{ $location['location_name'] }}</flux:heading>
                                <flux:text size="sm" variant="subtle">
                                    Base capacity: {{ $location['base_capacity'] }}
                                </flux:text>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:text variant="strong" size="xl">{{ $location['total_present'] }}</flux:text>
                                    @if($location['visitor_count'] > 0)
                                        <flux:badge icon="user-circle" size="sm" color="sky" inset="top bottom">{{ $location['visitor_count'] }}</flux:badge>
                                    @endif
                                </div>
                                <flux:text size="sm" :color="$location['utilization_pct'] > 100 ? 'red' : null" :variant="$location['utilization_pct'] > 100 ? null : 'subtle'">
                                    {{ $location['utilization_pct'] }}% utilization
                                </flux:text>
                            </div>
                        </div>

                        @if ($location['home_users']->isNotEmpty() || $location['visitor_users']->isNotEmpty())
                            <flux:separator class="my-3" />
                            <div class="flex flex-wrap gap-1">
                                @foreach ($location['home_users'] as $user)
                                    <flux:badge size="sm">{{ $user->surname }}</flux:badge>
                                @endforeach
                                @foreach ($location['visitor_users'] as $user)
                                    <flux:badge icon="user-circle" size="sm" color="sky">{{ $user->surname }}</flux:badge>
                                @endforeach
                            </div>
                        @else
                            <flux:text size="sm" variant="subtle" class="italic">No one present</flux:text>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="period">
            <div class="flex items-center gap-4 mb-6">
                <flux:date-picker mode="range" wire:model.live="range" with-today />
                @if ($aggregation === 'weekly')
                    <flux:badge color="sky">Showing weekly averages</flux:badge>
                @endif
            </div>
            <flux:text size="sm" variant="subtle" class="mb-4">
                @if ($aggregation === 'weekly')
                    Shows average weekly occupancy for each location. Colour intensity indicates utilization.
                @else
                    Shows total occupancy for each location over the selected period. Colour intensity indicates utilization.
                @endif
            </flux:text>

            <div class="overflow-x-auto">
                <div class="grid gap-1 p-0.5 rounded-lg min-w-max" style="grid-template-columns: 150px repeat({{ count($days) }}, minmax(70px, 1fr));">
                    {{-- Header row --}}
                    <div class="p-2">
                        <flux:text variant="strong">Location</flux:text>
                    </div>
                    @foreach ($days as $day)
                        <div class="p-2 text-center">
                            @if ($aggregation === 'weekly')
                                <flux:text variant="strong" class="text-xs">W/C</flux:text>
                                <flux:text class="text-xs block">{{ $day['date']->format('j M') }}</flux:text>
                            @else
                                <flux:text variant="strong" class="text-xs">{{ $day['date']->format('D') }}</flux:text>
                                <flux:text class="text-xs block">{{ $day['date']->format('j/n') }}</flux:text>
                            @endif
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
                                    <span class="font-medium">{{ $dayData['total_present'] }}</span>
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
            <div class="flex items-center gap-4 mb-6">
                <flux:date-picker mode="range" wire:model.live="range" with-today />
            </div>
            <flux:text size="sm" variant="subtle" class="mb-4">
                Summary statistics across the selected period. Use these figures to demonstrate space utilization.
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
