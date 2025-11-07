<div>
    <flux:heading size="xl">Profile Settings</flux:heading>
    <flux:subheading>Set your default location and work category to save time when planning your week.</flux:subheading>

    <flux:spacer class="mt-6" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <flux:card>
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Default Location</flux:label>
                    <flux:description>This location will be pre-filled for new entries.</flux:description>
                    <flux:select variant="combobox" placeholder="Select a default location..." wire:model.live="default_location">
                        @foreach ($locations as $location)
                            <flux:select.option value="{{ $location->value }}">{{ $location->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Default Work Category</flux:label>
                    <flux:description>What do you typically work on? (e.g., "Active Directory", "Support Tickets")</flux:description>
                    <flux:input wire:model.live="default_category" placeholder="e.g., Active Directory, Support Tickets..." />
                </flux:field>

                <flux:button variant="primary" wire:click="save">
                    Save Defaults
                </flux:button>
            </div>
        </flux:card>

        <flux:card>
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <div>
                        <flux:heading size="lg">API Access</flux:heading>
                        <flux:subheading>Generate API tokens to access your planning data via Power BI or other tools.</flux:subheading>
                    </div>
                    @admin
                        <flux:field variant="inline">
                            <flux:label>View All Tokens</flux:label>
                            <flux:switch wire:model.live="showAllTokens" />
                        </flux:field>
                    @endadmin
                </div>

                @if($this->tokens->isNotEmpty())
                    <flux:table>
                        <flux:table.columns>
                            @if(auth()->user()->isAdmin() && $showAllTokens)
                                <flux:table.column>Owner</flux:table.column>
                            @endif
                            <flux:table.column>Name</flux:table.column>
                            <flux:table.column>Last Used</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach($this->tokens as $token)
                                <flux:table.row :key="$token->id">
                                    @if(auth()->user()->isAdmin() && $showAllTokens)
                                        <flux:table.cell>{{ $token->tokenable->full_name }}</flux:table.cell>
                                    @endif
                                    <flux:table.cell>
                                        <flux:text variant="strong" wire:click="selectToken({{ $token->id }})" class="cursor-pointer text-bold">
                                            {{ $token->name }}
                                        </flux:text>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        {{ $token->last_used_at ? $token->last_used_at->format('M j, Y') : 'Never' }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:button
                                            size="sm"
                                            icon="trash"
                                            variant="danger"
                                            wire:click="revokeToken({{ $token->id }})"
                                            wire:confirm="Are you sure you want to revoke this token? Any applications using it will lose access."
                                        >
                                        </flux:button>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:text>No API tokens yet. Generate one to get started.</flux:text>
                @endif

                <flux:modal.trigger name="create-token">
                    <flux:button>Generate New Token</flux:button>
                </flux:modal.trigger>
            </div>
        </flux:card>
    </div>

    {{-- API Documentation Section --}}
    @if($this->tokens->isNotEmpty() && $this->selectedToken)
        <flux:spacer class="mt-6" />

        <flux:card>
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">How to Use Your API Token</flux:heading>
                    <flux:subheading>Using token: <span class="font-semibold">{{ $this->selectedToken->name }}</span></flux:subheading>
                </div>

                <flux:tab.group>
                    <flux:tabs>
                        <flux:tab name="cli">CLI</flux:tab>
                        <flux:tab name="powerbi">PowerBI</flux:tab>
                    </flux:tabs>

                    {{-- CLI Tab --}}
                    <flux:tab.panel name="cli">
                        <div class="space-y-6">
                            <flux:text>Copy and paste these examples to access your planning data from the command line.</flux:text>

                            @foreach($this->getAvailableEndpoints($this->selectedToken->abilities) as $endpoint)
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <flux:heading size="md">{{ $endpoint['name'] }}</flux:heading>
                                        <div class="flex gap-2 items-center">
                                            <flux:badge size="sm" variant="outline">{{ $endpoint['method'] }}</flux:badge>
                                            <flux:badge size="sm" variant="subtle">{{ $endpoint['ability'] }}</flux:badge>
                                        </div>
                                    </div>
                                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">{{ $endpoint['description'] }}</flux:text>

                                    @if($endpoint['method'] === 'GET')
                                        <flux:field>
                                            <flux:label>Request</flux:label>
                                            <flux:input
                                                value="curl -H 'Authorization: Bearer YOUR_TOKEN_HERE' {{ $this->baseUrl }}{{ $endpoint['path'] }}"
                                                readonly
                                                copyable
                                                class="font-mono text-sm"
                                            />
                                        </flux:field>
                                    @elseif($endpoint['method'] === 'POST')
                                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400 mb-4">
                                            All requests use the same format: an <code class="px-1 py-0.5 bg-zinc-100 dark:bg-zinc-800 rounded">entries</code> array. Send one or more entries in a single request.
                                        </flux:text>

                                        <flux:field>
                                            <flux:label>Create New Entry</flux:label>
                                            <flux:textarea
                                                readonly
                                                rows="7"
                                                class="font-mono text-xs"
                                            >curl -X POST '{{ $this->baseUrl }}{{ $endpoint['path'] }}' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE' \
  -H 'Content-Type: application/json' \
  -d '{
  "entries": [
    {"entry_date":"2025-11-10","location":"jws","note":"Working on API integration"}
  ]
}'</flux:textarea>
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>Update Entry by ID</flux:label>
                                            <flux:textarea
                                                readonly
                                                rows="7"
                                                class="font-mono text-xs"
                                            >curl -X POST '{{ $this->baseUrl }}{{ $endpoint['path'] }}' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE' \
  -H 'Content-Type: application/json' \
  -d '{
  "entries": [
    {"id":123,"entry_date":"2025-11-10","location":"jwn","note":"Updated location"}
  ]
}'</flux:textarea>
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>Update Entry by Date</flux:label>
                                            <flux:textarea
                                                readonly
                                                rows="7"
                                                class="font-mono text-xs"
                                            >curl -X POST '{{ $this->baseUrl }}{{ $endpoint['path'] }}' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE' \
  -H 'Content-Type: application/json' \
  -d '{
  "entries": [
    {"entry_date":"2025-11-10","location":"rankine","note":"Updated via date matching"}
  ]
}'</flux:textarea>
                                            <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400 mt-2">
                                                If an entry exists for this date, it will be updated. Otherwise, a new entry is created.
                                            </flux:text>
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>Create/Update Multiple Entries</flux:label>
                                            <flux:textarea
                                                readonly
                                                rows="9"
                                                class="font-mono text-xs"
                                            >curl -X POST '{{ $this->baseUrl }}{{ $endpoint['path'] }}' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE' \
  -H 'Content-Type: application/json' \
  -d '{
  "entries": [
    {"entry_date":"2025-11-10","location":"jws","note":"Day 1"},
    {"entry_date":"2025-11-11","location":"jwn","note":"Day 2"},
    {"id":456,"entry_date":"2025-11-12","location":"rankine","note":"Day 3 (update by ID)"}
  ]
}'</flux:textarea>
                                        </flux:field>
                                    @elseif($endpoint['method'] === 'DELETE')
                                        <flux:field>
                                            <flux:label>Request</flux:label>
                                            <flux:textarea
                                                readonly
                                                rows="3"
                                                class="font-mono text-xs"
                                            >curl -X DELETE '{{ $this->baseUrl }}/api/v1/plan/123' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE'</flux:textarea>
                                        </flux:field>
                                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                                            Replace <code class="px-1 py-0.5 bg-zinc-100 dark:bg-zinc-800 rounded">123</code> with the entry ID you want to delete.
                                        </flux:text>
                                    @endif

                                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                                        Replace <code class="px-1 py-0.5 bg-zinc-100 dark:bg-zinc-800 rounded">YOUR_TOKEN_HERE</code> with your actual token. You received this when you first created the token.
                                    </flux:text>
                                </div>

                                @if(!$loop->last)
                                    <flux:separator />
                                @endif
                            @endforeach
                        </div>
                    </flux:tab.panel>

                    {{-- PowerBI Tab --}}
                    <flux:tab.panel name="powerbi">
                        <div class="space-y-6">
                            {{-- Getting Started --}}
                            <div>
                                <flux:heading size="md">Getting Started</flux:heading>
                                <flux:text>Power BI lets you create dashboards and reports from your planning data. If you don't have Power BI Desktop installed, you can download it from the Microsoft website.</flux:text>
                            </div>

                            <flux:separator />

                            {{-- Connect to Your Data --}}
                            <div class="space-y-4">
                                <flux:heading size="md">Connect to Your Data</flux:heading>
                                <flux:text>Follow these steps to connect Power BI to your planning data:</flux:text>

                                <div class="space-y-3 ml-4">
                                    <flux:text><strong>1.</strong> Open Power BI Desktop</flux:text>
                                    <flux:text><strong>2.</strong> Click <strong>"Get Data"</strong> in the ribbon</flux:text>
                                    <flux:text><strong>3.</strong> Select <strong>"Web"</strong> from the list</flux:text>
                                    <flux:text><strong>4.</strong> Click <strong>"Advanced"</strong></flux:text>
                                    <flux:text><strong>5.</strong> Enter the URL for the data you want:</flux:text>

                                    <div class="ml-6 space-y-2">
                                        @foreach($this->getAvailableEndpoints($this->selectedToken->abilities) as $endpoint)
                                            <flux:field>
                                                <flux:label>{{ $endpoint['name'] }}</flux:label>
                                                <flux:input
                                                    value="{{ $this->baseUrl }}{{ $endpoint['path'] }}"
                                                    readonly
                                                    copyable
                                                    class="font-mono text-sm"
                                                />
                                            </flux:field>
                                        @endforeach
                                    </div>

                                    <flux:text><strong>6.</strong> In the <strong>"HTTP request header parameters"</strong> section, click <strong>"Add header"</strong></flux:text>
                                    <flux:text class="ml-6">• Header name: <code class="px-2 py-1 bg-zinc-100 dark:bg-zinc-800 rounded">Authorization</code></flux:text>
                                    <div class="ml-6">
                                        <flux:field>
                                            <flux:label>Header value (use your actual token):</flux:label>
                                            <flux:input
                                                value="Bearer YOUR_TOKEN_HERE"
                                                readonly
                                                copyable
                                                class="font-mono text-sm"
                                            />
                                        </flux:field>
                                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400 mt-1">
                                            Replace <code class="px-1 py-0.5 bg-zinc-100 dark:bg-zinc-800 rounded">YOUR_TOKEN_HERE</code> with the token you received when you created it (starts with a number like "1|...")
                                        </flux:text>
                                    </div>

                                    <flux:text><strong>7.</strong> For authentication, select <strong>"Anonymous"</strong> (the token is in the header above)</flux:text>
                                    <flux:text><strong>8.</strong> Click <strong>"OK"</strong></flux:text>
                                </div>
                            </div>

                            <flux:separator />

                            {{-- Transform Your Data --}}
                            <div class="space-y-4">
                                <flux:heading size="md">Transform Your Data</flux:heading>
                                <flux:text>After connecting, the Power Query Editor will open automatically. Here's how to work with your data:</flux:text>

                                <div class="space-y-3 ml-4">
                                    <flux:text><strong>1.</strong> You'll see your data as JSON. Look for columns with <strong>Table</strong> or <strong>Record</strong> values</flux:text>
                                    <flux:text><strong>2.</strong> Click the <strong>expand icon</strong> (two arrows) next to column headers like "days" or "team_rows"</flux:text>
                                    <flux:text><strong>3.</strong> Select which columns you want to include in your data</flux:text>
                                    <flux:text><strong>4.</strong> Click <strong>"OK"</strong> to expand the data</flux:text>
                                    <flux:text><strong>5.</strong> Repeat for any nested columns</flux:text>
                                    <flux:text><strong>6.</strong> When you're happy with your data, click <strong>"Close & Apply"</strong> in the ribbon</flux:text>
                                </div>
                            </div>

                            <flux:separator />

                            {{-- Create Your First Visual --}}
                            <div class="space-y-4">
                                <flux:heading size="md">Create Your First Visual</flux:heading>
                                <flux:text>Now you can create reports! Here are some suggestions based on your access:</flux:text>

                                <div class="space-y-3 ml-4">
                                    @if(in_array('view:own-plan', $this->selectedToken->abilities) && !in_array('view:team-plans', $this->selectedToken->abilities) && !in_array('view:all-plans', $this->selectedToken->abilities))
                                        <flux:text><strong>Personal Calendar:</strong> Use a calendar visual to show your locations over the next two weeks</flux:text>
                                    @else
                                        <flux:text><strong>Team Matrix:</strong> Create a matrix visual with team members as rows and dates as columns, showing locations</flux:text>
                                        <flux:text><strong>Location Chart:</strong> Use a stacked bar chart to show how many people are at each location per day</flux:text>
                                        <flux:text><strong>Coverage Heatmap:</strong> Create a matrix with conditional formatting to highlight days with low coverage</flux:text>
                                    @endif
                                </div>
                            </div>

                            <flux:separator />

                            {{-- Your Available Data Sources --}}
                            <div class="space-y-4">
                                <flux:heading size="md">Your Available Data Sources</flux:heading>
                                <flux:text>Based on your token permissions, you have access to the following data sources:</flux:text>

                                <div class="space-y-3">
                                    @foreach($this->getAvailableEndpoints($this->selectedToken->abilities) as $endpoint)
                                        <div class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded">
                                            <div class="flex items-center justify-between mb-2">
                                                <flux:text class="font-semibold">{{ $endpoint['name'] }}</flux:text>
                                                <flux:badge size="sm" variant="subtle">{{ $endpoint['ability'] }}</flux:badge>
                                            </div>
                                            <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400 mb-2">{{ $endpoint['description'] }}</flux:text>
                                            <flux:input
                                                value="{{ $this->baseUrl }}{{ $endpoint['path'] }}"
                                                readonly
                                                copyable
                                                class="font-mono text-xs"
                                            />
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </flux:tab.panel>
                </flux:tab.group>
            </div>
        </flux:card>
    @endif

    {{-- Token Creation Modal --}}
    <flux:modal name="create-token" variant="flyout" wire:model="showTokenModal">
        @if($generatedToken)
            {{-- Token Display Section --}}
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Token Generated!</flux:heading>
                    <flux:text class="mt-2">
                        Copy your token now. For security reasons, you won't be able to see it again.
                    </flux:text>
                </div>

                <flux:field>
                    <flux:label>Your API Token</flux:label>
                    <flux:input
                        value="{{ $generatedToken }}"
                        readonly
                        copyable
                        class="font-mono text-sm"
                    />
                </flux:field>

                <div class="flex justify-end">
                    <flux:button wire:click="closeTokenModal">Done</flux:button>
                </div>
            </div>
        @else
            {{-- Token Creation Form --}}
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Generate API Token</flux:heading>
                    <flux:text class="mt-2">
                        Create a new API token for accessing your planning data via Power BI or other tools.
                    </flux:text>
                </div>

                {{-- Show which abilities will be assigned --}}
                <flux:callout variant="info">
                    <div class="space-y-2">
                        <flux:text><strong>Automatic Permissions:</strong></flux:text>
                        <div class="flex gap-1 flex-wrap">
                            @foreach($this->determineTokenAbilities() as $ability)
                                <flux:badge size="sm">{{ $ability }}</flux:badge>
                            @endforeach
                        </div>
                        @if(auth()->user()->isAdmin())
                            <flux:text size="sm">As an admin, you can access all organizational data.</flux:text>
                        @elseif(auth()->user()->isManager())
                            <flux:text size="sm">As a manager, you can access your team's data.</flux:text>
                        @else
                            <flux:text size="sm">You can access your own planning data.</flux:text>
                        @endif
                    </div>
                </flux:callout>

                <flux:field>
                    <flux:label>Token Name</flux:label>
                    <flux:input
                        wire:model="newTokenName"
                        placeholder="e.g., Power BI - Team Dashboard"
                    />
                    <flux:error name="newTokenName" />
                </flux:field>

                <div class="flex gap-2 justify-end">
                    <flux:button wire:click="closeTokenModal" variant="ghost">Cancel</flux:button>
                    <flux:button wire:click="createToken" variant="primary">Generate Token</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
