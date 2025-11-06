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

                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove>Save Defaults</span>
                    <span wire:loading>Saving...</span>
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
                            <flux:table.column>Abilities</flux:table.column>
                            <flux:table.column>Created</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach($this->tokens as $token)
                                <flux:table.row :key="$token->id">
                                    @if(auth()->user()->isAdmin() && $showAllTokens)
                                        <flux:table.cell>{{ $token->tokenable->full_name }}</flux:table.cell>
                                    @endif
                                    <flux:table.cell>{{ $token->name }}</flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex gap-1 flex-wrap">
                                            @foreach($token->abilities as $ability)
                                                <flux:badge size="sm" variant="subtle">{{ $ability }}</flux:badge>
                                            @endforeach
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        {{ $token->created_at->format('M j, Y') }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            wire:click="revokeToken({{ $token->id }})"
                                            wire:confirm="Are you sure you want to revoke this token? Any applications using it will lose access."
                                        >
                                            Revoke
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
