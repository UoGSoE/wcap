<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    protected $signature = 'app:create-user';

    protected $description = 'Create a new user account';

    public function handle(): int
    {
        $username = text(
            label: 'Username',
            placeholder: 'e.g. jsmith',
            required: true,
            validate: fn (string $value) => User::where('username', $value)->exists()
                ? 'This username is already taken.'
                : null,
        );

        $email = text(
            label: 'Email address',
            placeholder: 'e.g. john.smith@example.ac.uk',
            required: true,
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'Please enter a valid email address.',
                User::where('email', $value)->exists() => 'This email is already in use.',
                default => null,
            },
        );

        $email = Str::lower($email);

        $localPart = Str::before($email, '@');
        $parts = explode('.', $localPart);
        $suggestedForenames = count($parts) > 1 ? ucfirst($parts[0]) : '';
        $suggestedSurname = ucfirst(end($parts));

        $surname = text(
            label: 'Surname',
            default: $suggestedSurname,
            required: true,
        );

        $forenames = text(
            label: 'Forename(s)',
            default: $suggestedForenames,
            required: true,
        );

        $isAdmin = confirm(
            label: 'Should this user be an administrator?',
            default: false,
        );

        User::create([
            'username' => $username,
            'email' => $email,
            'surname' => $surname,
            'forenames' => $forenames,
            'is_admin' => $isAdmin,
            'is_staff' => true,
            'password' => Hash::make(Str::random(64)),
        ]);

        $this->info("User '{$username}' created successfully.");

        return self::SUCCESS;
    }
}
