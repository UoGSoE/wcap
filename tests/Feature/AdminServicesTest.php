<?php

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('admin can view service management page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->assertOk()
        ->assertSee('Service Management')
        ->assertSee('Create New Service');
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('non-admin cannot access service management page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    actingAs($user);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->assertForbidden();
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('admin can see all services in the list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager1 = User::factory()->create(['surname' => 'One', 'forenames' => 'Manager']);
    $manager2 = User::factory()->create(['surname' => 'Two', 'forenames' => 'Manager']);

    $service1 = Service::factory()->create([
        'name' => 'Active Directory Service',
        'manager_id' => $manager1->id,
    ]);

    $service2 = Service::factory()->create([
        'name' => 'Email Service',
        'manager_id' => $manager2->id,
    ]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->assertOk()
        ->assertSee('Active Directory Service')
        ->assertSee('Email Service')
        ->assertSee('One, Manager')
        ->assertSee('Two, Manager');
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('admin can create a new service', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('createService')
        ->set('serviceName', 'New Service')
        ->set('managerId', $manager->id)
        ->set('selectedUserIds', [$member1->id, $member2->id])
        ->call('save')
        ->assertSet('editingServiceId', null);

    $service = Service::where('name', 'New Service')->firstOrFail();
    expect($service->users)->toHaveCount(2);
    expect($service->users->pluck('id')->toArray())->toContain($member1->id, $member2->id);
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('service name must be unique when creating', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    Service::factory()->create(['name' => 'Existing Service', 'manager_id' => $manager->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('createService')
        ->set('serviceName', 'Existing Service')
        ->set('managerId', $manager->id)
        ->call('save')
        ->assertHasErrors(['serviceName' => 'unique']);

    $this->assertEquals(1, Service::where('name', 'Existing Service')->count());
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('admin can edit an existing service', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $oldManager = User::factory()->create();
    $newManager = User::factory()->create();

    $service = Service::factory()->create([
        'name' => 'Original Name',
        'manager_id' => $oldManager->id,
    ]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('editService', $service->id)
        ->assertSet('editingServiceId', $service->id)
        ->assertSet('serviceName', 'Original Name')
        ->assertSet('managerId', $oldManager->id)
        ->set('serviceName', 'Updated Name')
        ->set('managerId', $newManager->id)
        ->call('save')
        ->assertSet('editingServiceId', null);

    $service->refresh();

    expect($service->name)->toBe('Updated Name');
    expect($service->manager_id)->toBe($newManager->id);
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('service name must be unique when updating but can keep same name', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $service1 = Service::factory()->create(['name' => 'Service One', 'manager_id' => $manager->id]);
    $service2 = Service::factory()->create(['name' => 'Service Two', 'manager_id' => $manager->id]);

    actingAs($admin);

    // Try to change service2's name to service1's name - should fail
    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('editService', $service2->id)
        ->set('serviceName', 'Service One')
        ->call('save')
        ->assertHasErrors(['serviceName' => 'unique']);

    // Keeping the same name should work
    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('editService', $service2->id)
        ->set('serviceName', 'Service Two')
        ->call('save')
        ->assertHasNoErrors();
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('admin can update service members', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $service = Service::factory()->create(['manager_id' => $manager->id]);

    $oldMember1 = User::factory()->create();
    $oldMember2 = User::factory()->create();
    $newMember1 = User::factory()->create();
    $newMember2 = User::factory()->create();

    $service->users()->attach([$oldMember1->id, $oldMember2->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('editService', $service->id)
        ->set('selectedUserIds', [$newMember1->id, $newMember2->id])
        ->call('save');

    $service->refresh();

    expect($service->users)->toHaveCount(2);
    expect($service->users->pluck('id')->toArray())->toContain($newMember1->id, $newMember2->id);
    expect($service->users->pluck('id')->toArray())->not->toContain($oldMember1->id, $oldMember2->id);
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('admin can delete a service without transferring members', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $service = Service::factory()->create(['manager_id' => $manager->id]);
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    $service->users()->attach([$member1->id, $member2->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('confirmDelete', $service->id)
        ->assertSet('showDeleteModal', true)
        ->assertSet('deletingServiceId', $service->id)
        ->call('deleteService');

    $this->assertDatabaseMissing('services', ['id' => $service->id]);
    $this->assertDatabaseMissing('service_user', ['service_id' => $service->id]);
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('admin can delete a service and transfer members to another service', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $serviceToDelete = Service::factory()->create(['name' => 'Old Service', 'manager_id' => $manager->id]);
    $targetService = Service::factory()->create(['name' => 'New Service', 'manager_id' => $manager->id]);

    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    $serviceToDelete->users()->attach([$member1->id, $member2->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('confirmDelete', $serviceToDelete->id)
        ->set('transferServiceId', $targetService->id)
        ->call('deleteService');

    $this->assertDatabaseMissing('services', ['id' => $serviceToDelete->id]);

    $targetService->refresh();
    expect($targetService->users)->toHaveCount(2);
    expect($targetService->users->pluck('id')->toArray())->toContain($member1->id, $member2->id);
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('validation requires service name', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('createService')
        ->set('serviceName', '')
        ->set('managerId', $manager->id)
        ->call('save')
        ->assertHasErrors(['serviceName' => 'required']);
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('validation requires manager', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('createService')
        ->set('serviceName', 'Test Service')
        ->set('managerId', null)
        ->call('save')
        ->assertHasErrors(['managerId' => 'required']);
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('admin can cancel editing', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $service = Service::factory()->create(['manager_id' => $manager->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('editService', $service->id)
        ->assertSet('editingServiceId', $service->id)
        ->call('cancelEdit')
        ->assertSet('editingServiceId', null)
        ->assertSet('serviceName', '')
        ->assertSet('managerId', null);
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('admin can close delete modal', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $service = Service::factory()->create(['manager_id' => $manager->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminServices::class)
        ->call('confirmDelete', $service->id)
        ->assertSet('showDeleteModal', true)
        ->call('closeDeleteModal')
        ->assertSet('showDeleteModal', false)
        ->assertSet('deletingServiceId', null);
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');

test('service list shows correct member counts', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $service1 = Service::factory()->create(['name' => 'Service One', 'manager_id' => $manager->id]);
    $service2 = Service::factory()->create(['name' => 'Service Two', 'manager_id' => $manager->id]);

    $service1->users()->attach([
        User::factory()->create()->id,
        User::factory()->create()->id,
        User::factory()->create()->id,
    ]);

    $service2->users()->attach([
        User::factory()->create()->id,
    ]);

    actingAs($admin);

    $component = Livewire::test(\App\Livewire\AdminServices::class);

    $services = $component->viewData('services');

    expect($services->firstWhere('name', 'Service One')->users)->toHaveCount(3);
    expect($services->firstWhere('name', 'Service Two')->users)->toHaveCount(1);
})->skip(fn() => ! config('wcap.services_enabled'), 'Services feature is disabled (WCAP_SERVICES_ENABLED=false)');
