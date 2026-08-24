<div>
    <div class="mb-6 flex justify-between items-center gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">Team Report</flux:heading>
                @if (! $isCurrentWeek)
                    <flux:badge color="amber" size="sm">Week of {{ $resolvedWeekStart->format('j M Y') }}</flux:badge>
                @endif
            </div>
            <flux:subheading>Where everyone is over the {{ $range === 'month' ? 'four' : 'two' }} weeks starting {{ $resolvedWeekStart->format('jS F') }}. Filter by team to narrow it down.</flux:subheading>
        </div>
        @adminOrManager
            <div class="flex gap-2">
                <flux:button
                    href="{{ route('manager.entries') }}"
                    wire:navigate
                    variant="primary"
                    icon="pencil-square"
                >
                    Edit Plans
                </flux:button>
                <flux:button
                    wire:click="exportAll"
                    class="cursor-pointer"
                >
                    Download Excel
                </flux:button>
            </div>
        @endadminOrManager
    </div>

    <flux:spacer class="mt-6"/>

    <div class="mb-4 flex justify-between items-center gap-4">
        <flux:field class="flex-1">
            <flux:label class="sr-only">Filter by teams</flux:label>
            <flux:pillbox
                wire:model.live="selectedTeams"
                multiple
                placeholder="Filter by team(s)..."
                searchable
            >
            @foreach ($availableTeams as $team)
                <flux:pillbox.option :value="$team->id">{{ $team->name }}</flux:pillbox.option>
            @endforeach
            </flux:pillbox>
        </flux:field>
        <flux:field>
            <flux:label class="sr-only">Week starting</flux:label>
            <flux:date-picker wire:model.live="weekStart" with-today />
        </flux:field>
        @if (! $isCurrentWeek)
            <flux:button
                wire:click="goToToday"
                variant="ghost"
                size="sm"
                icon="calendar-days"
            >
                Today
            </flux:button>
        @endif
        <flux:radio.group wire:model.live="range" variant="segmented" size="sm">
            <flux:radio value="fortnight" label="Two weeks" />
            <flux:radio value="month" label="Month" />
        </flux:radio.group>
    </div>

    <flux:tab.group>
        <flux:tabs wire:model.live="tab">
            <flux:tab name="team">My Reports</flux:tab>
            <flux:tab name="location">Area Supported</flux:tab>
            <flux:tab name="coverage">Coverage</flux:tab>
            {{-- Hidden at the request of the stakeholder (24/08/2026). Remove this guard and the matching one on the panel below to restore it. --}}
            @if (false)
                @servicesEnabled
                    <flux:tab name="service-availability">Service Availability</flux:tab>
                @endservicesEnabled
            @endif
        </flux:tabs>

        <flux:tab.panel name="team">
            <div class="flex items-center justify-between mb-4">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    View: <span class="font-medium">{{ $showLocation ? 'Locations' : 'Work Notes' }}</span>
                </flux:text>
                <flux:field variant="inline">
                    <flux:label>Show Locations</flux:label>
                    <flux:switch wire:model.live="showLocation" />
                </flux:field>
            </div>

            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="sticky left-0 bg-white dark:bg-zinc-800">Team Member</flux:table.column>
                        @foreach ($days as $day)
                            <flux:table.column align="center" class="{{ $range === 'month' && $day['date']->isMonday() ? 'border-l border-zinc-200 dark:border-zinc-700' : '' }}">
                                <x-report-day-heading :day="$day" :compact="$range === 'month'" />
                            </flux:table.column>
                        @endforeach
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($teamRows as $row)
                            <flux:table.row :key="$row['member_id']">
                                <flux:table.cell class="sticky left-0 font-medium bg-white dark:bg-zinc-800">
                                    {{ $row['name'] }}
                                </flux:table.cell>
                                @foreach ($row['days'] as $dayData)
                                    <flux:table.cell class="text-center {{ $range === 'month' && $dayData['date']->isMonday() ? 'border-l border-zinc-200 dark:border-zinc-700' : '' }}">
                                        @if ($dayData['state'] === 'planned')
                                            @if ($showLocation)
                                                <flux:tooltip :content="$dayData['note']">
                                                    <flux:badge size="sm" inset="top bottom" class="cursor-help">
                                                        {{ $dayData['location_short'] }}
                                                    </flux:badge>
                                                </flux:tooltip>
                                            @else
                                                <flux:text class="text-sm">{{ $dayData['note'] }}</flux:text>
                                            @endif
                                        @elseif ($dayData['state'] === 'away')
                                            <flux:badge size="sm" color="sky" variant="outline" inset="top bottom">Away</flux:badge>
                                        @else
                                            <flux:tooltip content="No record">
                                                <flux:badge size="sm" color="red" variant="outline" inset="top bottom">-</flux:badge>
                                            </flux:tooltip>
                                        @endif
                                    </flux:table.cell>
                                @endforeach
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="{{ count($days) + 1 }}" class="text-center text-zinc-500">
                                    No team members found
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="location">
            <div class="grid grid-cols-1 gap-6">
                @foreach ($locationDays as $dayData)
                    <div>
                        <flux:heading size="lg">{{ $dayData['date']->format('l, F jS') }}</flux:heading>
                        <flux:spacer class="mt-4"/>

                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach ($dayData['locations'] as $location)
                                <flux:callout :icon="$location['show_danger'] ? 'x-circle' : 'check-circle'" :heading="$location['label']" :variant="$location['show_danger'] ? 'danger' : 'secondary'">
                                    <flux:callout.text>
                                        @if ($location['show_danger'])
                                            <flux:text variant="strong">No staff</flux:text>
                                        @elseif (!empty($location['members']))
                                            <ul class="space-y-1">
                                                @foreach ($location['members'] as $memberData)
                                                    <li>
                                                        <flux:text>
                                                            {{ $memberData['name'] }}
                                                            @if ($memberData['note'])
                                                                <span class="text-xs text-zinc-500">- {{ $memberData['note'] }}</span>
                                                            @endif
                                                        </flux:text>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </flux:callout.text>
                                </flux:callout>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="coverage">
            <flux:subheading>Location coverage at a glance</flux:subheading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mt-2 mb-6">
                Gray cells indicate at least one person supporting that area. Gaps mean no coverage.
            </flux:text>

            <div class="overflow-x-auto">
            <div class="grid gap-2 p-0.5 rounded-lg" style="grid-template-columns: 150px repeat({{ count($days) }}, 1fr);">
                {{-- Header row --}}
                <div>
                    <flux:text variant="strong"></flux:text>
                </div>
                @foreach ($days as $day)
                    <div class="text-center">
                        <x-report-day-heading :day="$day" :compact="$range === 'month'" />
                    </div>
                @endforeach

                {{-- Location rows --}}
                @foreach ($coverageMatrix as $row)
                    <div>
                        <flux:text variant="strong">{{ $row['label'] }}</flux:text>
                    </div>
                    @foreach ($row['entries'] as $entry)
                        <div class="p-3 text-center text-sm font-medium {{ $entry['count'] > 0 ? 'bg-zinc-300 dark:bg-zinc-700' : '' }}">
                            @if ($entry['count'] > 0)
                                {{ $entry['count'] }}
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
            </div>
        </flux:tab.panel>

        {{-- Hidden at the request of the stakeholder (24/08/2026) - see the matching guard on the tab above. --}}
        @if (false)
        @servicesEnabled
            <flux:tab.panel name="service-availability">
                <flux:subheading>Service availability at a glance</flux:subheading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mt-2 mb-6">
                    Shows how many people on each service are available each day. Gray cells indicate at least one person available.
                </flux:text>

                <div class="overflow-x-auto">
                <div class="grid gap-2 p-0.5 rounded-lg" style="grid-template-columns: 200px repeat({{ count($days) }}, 1fr);">
                    {{-- Header row --}}
                    <div>
                        <flux:text variant="strong"></flux:text>
                    </div>
                    @foreach ($days as $day)
                        <div class="text-center">
                            <x-report-day-heading :day="$day" :compact="$range === 'month'" />
                        </div>
                    @endforeach

                    {{-- Service rows --}}
                    @foreach ($serviceAvailabilityMatrix as $row)
                        <div>
                            <flux:text variant="strong">{{ $row['label'] }}</flux:text>
                        </div>
                        @foreach ($row['entries'] as $entry)
                            <div class="p-3 text-center text-sm font-medium {{ $entry['count'] > 0 ? 'bg-zinc-300 dark:bg-zinc-700' : '' }}">
                                @if ($entry['count'] > 0)
                                    {{ $entry['count'] }}
                                @elseif ($entry['manager_only'])
                                    <flux:tooltip content="Coverage is only by the service manager">
                                        <flux:badge color="red" size="sm" inset="top bottom">Manager</flux:badge>
                                    </flux:tooltip>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
                </div>
            </flux:tab.panel>
        @endservicesEnabled
        @endif
    </flux:tab.group>
</div>
