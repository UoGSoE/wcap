<?php

use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;
use App\Enums\Location;
use App\Models\PlanEntry;

use function Pest\Laravel\actingAs;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('home page loads the plan editor component for non-managers', function () {
    $user = User::factory()->create();

    actingAs($user);

    $this->get(route('home'))->assertOk()->assertSeeLivewire('plan-entry-editor');
});

test('home page redirects to manager report page for managers', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    $this->get(route('home'))->assertRedirect(route('manager.report'));
});
