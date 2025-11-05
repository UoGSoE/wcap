<div>
    <flux:heading size="xl">Team Report</flux:heading>
    <flux:subheading>View your team's plans for the next two weeks</flux:subheading>

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

    @if ($availableTeams->isNotEmpty())
        <div class="mb-6">
        </div>
    @endif

    <flux:tab.group>
        <flux:tabs>
            <flux:tab name="team">My Reports</flux:tab>
            <flux:tab name="location">By Location</flux:tab>
            <flux:tab name="coverage">Coverage</flux:tab>
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
                                <flux:text variant="strong">{{ $day->format('D') }} {{ $day->format('jS') }}</flux:text>
                            </flux:table.column>
                        @endforeach
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($teamMembers as $member)
                            <flux:table.row :key="$member->id">
                                <flux:table.cell class="sticky left-0 font-medium">
                                    {{ $member->surname }}, {{ $member->forenames }}
                                </flux:table.cell>
                                @foreach ($days as $day)
                                    @php
                                        $dateKey = $day->format('Y-m-d');
                                        $entry = $planEntries->get($member->id)?->first(function ($entry) use ($dateKey) {
                                            return $entry->entry_date->format('Y-m-d') === $dateKey;
                                        });
                                    @endphp
                                    <flux:table.cell class="text-center">
                                        @if ($entry && $entry->location)
                                            @if ($showLocation)
                                                <flux:tooltip :content="$entry->note ?: 'No details'">
                                                    <flux:badge size="sm" inset="top bottom" class="cursor-help">
                                                        {{ $entry->location->shortLabel() }}
                                                    </flux:badge>
                                                </flux:tooltip>
                                            @else
                                                <flux:text class="text-sm">{{ $entry->note ?: '?' }}</flux:text>
                                            @endif
                                        @elseif ($entry && !$entry->location)
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
                @foreach ($days as $day)
                    @php
                        $dateKey = $day->format('Y-m-d');
                        $locationsForDay = $daysByLocation[$dateKey] ?? [];
                    @endphp
                    <div>
                        <flux:heading size="lg">{{ $day->format('l, F jS') }}</flux:heading>
                        <flux:spacer class="mt-4"/>

                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach ($locations as $location)
                                <flux:card>
                                    @if (empty($locationsForDay))
                                        <flux:text class="text-zinc-500">No plans recorded for this day</flux:text>
                                    @else
                                        @php
                                            $membersAtLocation = $locationsForDay[$location->value] ?? [];
                                        @endphp
                                        @if (!empty($membersAtLocation))
                                            <div>
                                                <flux:subheading class="flex items-center gap-2">
                                                    <flux:badge>
                                                        {{ $location->label() }}
                                                    </flux:badge>
                                                    <span class="text-sm text-zinc-500">({{ count($membersAtLocation) }})</span>
                                                </flux:subheading>
                                                <flux:spacer class="mt-2"/>
                                                <ul class="space-y-1">
                                                    @foreach ($membersAtLocation as $data)
                                                        <li>
                                                            <flux:text>
                                                                {{ $data['member']->surname }}, {{ $data['member']->forenames }}
                                                                @if ($data['note'])
                                                                    <span class="text-xs text-zinc-500">- {{ $data['note'] }}</span>
                                                                @endif
                                                            </flux:text>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    @endif
                                </flux:card>
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
                        <flux:text variant="strong">{{ $day->format('D') }} {{ $day->format('jS') }}</flux:text>
                    </div>
                @endforeach

                {{-- Location rows --}}
                @foreach ($locations as $location)
                    <div>
                        <flux:text variant="strong">{{ $location->label() }}</flux:text>
                    </div>
                    @foreach ($days as $day)
                        @php
                            $dateKey = $day->format('Y-m-d');
                            $count = $coverage[$location->value][$dateKey] ?? 0;
                        @endphp
                        <div class="p-3 text-center text-sm font-medium {{ $count > 0 ? 'bg-zinc-300 dark:bg-zinc-700' : '' }}">
                            @if ($count > 0)
                                {{ $count }}
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        </flux:tab.panel>
    </flux:tab.group>
</div>
