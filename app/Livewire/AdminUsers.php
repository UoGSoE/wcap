<?php

namespace App\Livewire;

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

class AdminUsers extends Component
{
    public ?int $editingUserId = null;

    public string $username = '';

    public string $email = '';

    public string $surname = '';

    public string $forenames = '';

    public bool $isAdmin = false;

    public bool $isStaff = true;

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    public ?int $deletingUserId = null;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->isAdmin()) {
            abort(403, 'You must be an admin to access this page.');
        }
    }

    public function render()
    {
        $users = User::with('managedTeams')->orderBy('surname')->orderBy('forenames')->get();

        return view('livewire.admin-users', [
            'users' => $users,
        ]);
    }

    public function createUser(): void
    {
        $this->editingUserId = -1;
        $this->username = '';
        $this->email = '';
        $this->surname = '';
        $this->forenames = '';
        $this->isAdmin = false;
        $this->isStaff = true;
        $this->showEditModal = true;
    }

    public function editUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->editingUserId = $userId;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->surname = $user->surname;
        $this->forenames = $user->forenames;
        $this->isAdmin = $user->is_admin;
        $this->isStaff = $user->is_staff;
        $this->showEditModal = true;
    }

    public function save(): void
    {
        $uniqueUsernameRule = $this->editingUserId === -1
            ? 'unique:users,username'
            : 'unique:users,username,'.$this->editingUserId;

        $uniqueEmailRule = $this->editingUserId === -1
            ? 'unique:users,email'
            : 'unique:users,email,'.$this->editingUserId;

        $validated = $this->validate([
            'username' => 'required|string|max:255|'.$uniqueUsernameRule,
            'email' => 'required|email|'.$uniqueEmailRule,
            'surname' => 'required|string|max:255',
            'forenames' => 'required|string|max:255',
            'isAdmin' => 'boolean',
            'isStaff' => 'boolean',
        ], [
            'username.required' => 'Username is required.',
            'username.unique' => 'This username is already taken.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already in use.',
            'surname.required' => 'Surname is required.',
            'forenames.required' => 'Forenames is required.',
        ]);

        if ($this->editingUserId === -1) {
            User::create([
                'username' => $validated['username'],
                'email' => $validated['email'],
                'surname' => $validated['surname'],
                'forenames' => $validated['forenames'],
                'is_admin' => $validated['isAdmin'],
                'is_staff' => $validated['isStaff'],
                'password' => Hash::make(Str::random(32)),
            ]);

            Flux::toast(
                heading: 'User created!',
                text: 'The user has been created successfully.',
                variant: 'success'
            );
        } else {
            $user = User::findOrFail($this->editingUserId);

            $updateData = [
                'username' => $validated['username'],
                'email' => $validated['email'],
                'surname' => $validated['surname'],
                'forenames' => $validated['forenames'],
                'is_admin' => $validated['isAdmin'],
                'is_staff' => $validated['isStaff'],
            ];

            $user->update($updateData);

            Flux::toast(
                heading: 'User updated!',
                text: 'The user has been updated successfully.',
                variant: 'success'
            );
        }

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->showEditModal = false;
        $this->editingUserId = null;
        $this->username = '';
        $this->email = '';
        $this->surname = '';
        $this->forenames = '';
        $this->isAdmin = false;
        $this->isStaff = true;
    }

    public function confirmDelete(int $userId): void
    {
        $this->deletingUserId = $userId;
        $this->showDeleteModal = true;
    }

    public function deleteUser(): void
    {
        $user = User::findOrFail($this->deletingUserId);

        $user->teams()->detach();
        $user->planEntries()->delete();
        $user->managedTeams()->update(['manager_id' => null]);
        $user->delete();

        Flux::toast(
            heading: 'User deleted!',
            text: 'The user has been deleted successfully.',
            variant: 'success'
        );

        $this->showDeleteModal = false;
        $this->deletingUserId = null;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingUserId = null;
    }
}
