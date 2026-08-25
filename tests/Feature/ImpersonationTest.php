<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can impersonate another user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($admin)->post(route('impersonate.start', $user));

    $response->assertRedirect(route('home'));
    expect(auth()->id())->toBe($user->id);
    expect(session('impersonator_id'))->toBe($admin->id);
});

test('a non-admin cannot impersonate another user', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false]);
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($nonAdmin)->post(route('impersonate.start', $user));

    $response->assertForbidden();
    expect(auth()->id())->toBe($nonAdmin->id);
    expect(session()->has('impersonator_id'))->toBeFalse();
});

test('impersonation is blocked in production even for admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    $this->app['env'] = 'production';

    $response = $this->withoutMiddleware(PreventRequestForgery::class)
        ->actingAs($admin)
        ->post(route('impersonate.start', $user));

    $response->assertNotFound();
    expect(auth()->id())->toBe($admin->id);
    expect(session()->has('impersonator_id'))->toBeFalse();
});

test('the admin users page shows an impersonate button for other users but not yourself', function () {
    $admin = User::factory()->create(['is_admin' => true, 'surname' => 'Admin', 'forenames' => 'Anna']);
    User::factory()->create(['surname' => 'Smith', 'forenames' => 'John']);

    $response = $this->actingAs($admin)->get(route('admin.users'));

    $response->assertSee('Impersonate Smith, John');
    $response->assertDontSee('Impersonate Admin, Anna');
});

test('stopping impersonation logs the admin back in as themselves', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($admin)->post(route('impersonate.start', $user));

    $response = $this->post(route('impersonate.stop'));

    $response->assertRedirect(route('home'));
    expect(auth()->id())->toBe($admin->id);
    expect(session()->has('impersonator_id'))->toBeFalse();
});

test('the sidebar shows a stop impersonating button only while impersonating', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($admin)->get(route('profile'))->assertDontSee('Stop impersonating');

    $this->post(route('impersonate.start', $user));

    $this->get(route('profile'))->assertSee('Stop impersonating');
});
