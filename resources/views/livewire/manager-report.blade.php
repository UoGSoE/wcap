<div>
    <div class="mb-6 flex justify-between items-center gap-4">
        <div>
            <flux:heading size="xl">Team Report</flux:heading>
            <flux:subheading>View your team's plans for the next two weeks</flux:subheading>
        </div>
        <flux:button
            wire:click="exportAll"
            class="cursor-pointer"
        >
            Download Excel
        </flux:button>
    </div>

    <flux:spacer class="mt-6"/>

    <div class="mb-4 flex justify-between items-center gap-4">
        <flux:pillbox
            wire:model.live="selectedTeams"
            multiple
            placeholder="Filter by team(s)..."
            searchable
            class="flex-1"
        >
            @foreach ($availableTeams as $team)
                <flux:pillbox.option :value="$team->id">{{ $team->name }}</flux:pillbox.option>
            @endforeach
        </flux:pillbox>
        @admin
            <flux:field variant="inline" class="">
                <flux:label>View All Users</flux:label>
                <flux:switch wire:model.live="showAllUsers" />
            </flux:field>
        @endadmin

    </div>

    <flux:tab.group>
        <flux:tabs>
            <flux:tab name="team">My Reports</flux:tab>
            <flux:tab name="location">By Location</flux:tab>
            <flux:tab name="coverage">Coverage</flux:tab>
            <flux:tab name="service-availability">Service Availability</flux:tab>
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
                        <flux:table.column class="sticky left-0">Team Member</flux:table.column>
                        @foreach ($days as $day)
                            <flux:table.column align="center">
                                <flux:text variant="strong">{{ $day['date']->format('D') }} {{ $day['date']->format('jS') }}</flux:text>
                            </flux:table.column>
                        @endforeach
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($teamRows as $row)
                            <flux:table.row :key="$row['member_id']">
                                <flux:table.cell class="sticky left-0 font-medium">
                                    {{ $row['name'] }}
                                </flux:table.cell>
                                @foreach ($row['days'] as $dayData)
                                    <flux:table.cell class="text-center">
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
                                <flux:callout :icon="empty($location['members']) ? 'x-circle' : 'check-circle'" :heading="$location['label']" :variant="empty($location['members']) ? 'danger' : 'secondary'">
                                    <flux:callout.text>
                                        @if (empty($location['members']))
                                            <flux:text variant="strong">No staff</flux:text>
                                        @else
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
                Gray cells indicate at least one person at that location. Gaps mean no coverage.
            </flux:text>

            <div class="grid gap-2 p-0.5 rounded-lg" style="grid-template-columns: 150px repeat(10, 1fr);">
                {{-- Header row --}}
                <div>
                    <flux:text variant="strong"></flux:text>
                </div>
                @foreach ($days as $day)
                    <div class="text-center">
                        <flux:text variant="strong">{{ $day['date']->format('D') }} {{ $day['date']->format('jS') }}</flux:text>
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
        </flux:tab.panel>

        <flux:tab.panel name="service-availability">
            <flux:subheading>Service availability at a glance</flux:subheading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mt-2 mb-6">
                Shows how many people on each service are available each day. Gray cells indicate at least one person available.
            </flux:text>

            <div class="grid gap-2 p-0.5 rounded-lg" style="grid-template-columns: 200px repeat(10, 1fr);">
                {{-- Header row --}}
                <div>
                    <flux:text variant="strong"></flux:text>
                </div>
                @foreach ($days as $day)
                    <div class="text-center">
                        <flux:text variant="strong">{{ $day['date']->format('D') }} {{ $day['date']->format('jS') }}</flux:text>
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
        </flux:tab.panel>
    </flux:tab.group>
</div>
