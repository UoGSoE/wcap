<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it creates a user with prompted values', function () {
    $this->artisan('app:create-user')
        ->expectsQuestion('Username', 'jsmith')
        ->expectsQuestion('Email address', 'John.Smith@example.ac.uk')
        ->expectsQuestion('Surname', 'Smith')
        ->expectsQuestion('Forename(s)', 'John')
        ->expectsConfirmation('Should this user be an administrator?', 'no')
        ->expectsOutputToContain("User 'jsmith' created successfully.")
        ->assertExitCode(0);

    $user = User::where('username', 'jsmith')->first();
    expect($user)->not->toBeNull();
    expect($user->email)->toBe('john.smith@example.ac.uk');
    expect($user->surname)->toBe('Smith');
    expect($user->forenames)->toBe('John');
    expect($user->is_admin)->toBeFalse();
    expect($user->is_staff)->toBeTrue();
    expect($user->password)->not->toBeEmpty();
});

test('it creates an admin user', function () {
    $this->artisan('app:create-user')
        ->expectsQuestion('Username', 'admin')
        ->expectsQuestion('Email address', 'admin@example.ac.uk')
        ->expectsQuestion('Surname', 'Admin')
        ->expectsQuestion('Forename(s)', 'Super')
        ->expectsConfirmation('Should this user be an administrator?', 'yes')
        ->assertExitCode(0);

    $user = User::where('username', 'admin')->first();
    expect($user->is_admin)->toBeTrue();
});

test('it suggests surname and forenames from email', function () {
    $this->artisan('app:create-user')
        ->expectsQuestion('Username', 'jdoe')
        ->expectsQuestion('Email address', 'Jane.Doe@example.ac.uk')
        ->expectsQuestion('Surname', 'Doe')
        ->expectsQuestion('Forename(s)', 'Jane')
        ->expectsConfirmation('Should this user be an administrator?', 'no')
        ->assertExitCode(0);

    $user = User::where('username', 'jdoe')->first();
    expect($user->surname)->toBe('Doe');
    expect($user->forenames)->toBe('Jane');
});

test('it lowercases the email', function () {
    $this->artisan('app:create-user')
        ->expectsQuestion('Username', 'tuser')
        ->expectsQuestion('Email address', 'Test.User@EXAMPLE.AC.UK')
        ->expectsQuestion('Surname', 'User')
        ->expectsQuestion('Forename(s)', 'Test')
        ->expectsConfirmation('Should this user be an administrator?', 'no')
        ->assertExitCode(0);

    $user = User::where('username', 'tuser')->first();
    expect($user->email)->toBe('test.user@example.ac.uk');
});
