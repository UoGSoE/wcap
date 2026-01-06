<?php

namespace App\Livewire;

use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use App\Services\PlanEntryImport;
use App\Services\PlanEntryRowValidator;
use DateTimeImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Ohffs\SimpleSpout\ExcelSheet;

class ImportPlanEntries extends Component
{
    use WithFileUploads;

    public $file;

    public array $validRows = [];

    public array $errorRows = [];

    public bool $showPreview = false;

    public int $creatingForRowIndex = -1;

    // Form fields
    public string $newUserForenames = '';

    public string $newUserSurname = '';

    public string $newUserEmail = '';

    public string $newUserUsername = '';

    public $newUserDefaultLocationId = null;

    public string $newUserDefaultCategory = '';

    public $newUserTeamId = null;

    public function updatedFile(): void
    {
        $this->validate([
            'file' => 'required|file|mimes:xlsx|max:1024',
        ]);

        $this->parseFile();
    }

    public function parseFile(): void
    {
        $rows = (new ExcelSheet)->trimmedImport($this->file->getRealPath());

        $this->validRows = [];
        $this->errorRows = [];
        $validator = new PlanEntryRowValidator;

        foreach ($rows as $index => $row) {
            $result = $validator->validate($row);

            if ($result->fails()) {
                if ($index === 0 && (! str_contains($row[0], '@'))) {
                    // probably a header row
                    continue;
                }
                $row[1] = $row[1] instanceof DateTimeImmutable ? $row[1]->format('d/m/Y') : $row[1];
                $this->errorRows[] = [
                    'row' => $index + 1,
                    'data' => $row,
                    'error' => $result->errors()->first(),
                ];

                continue;
            }

            $validated = $result->safe()->toArray();
            $user = User::where('email', $validated['email'])->first();

            $this->validRows[] = [
                'row' => $index + 1,
                'user_name' => $user->full_name,
                'email' => $validated['email'],
                'date' => $validated['date'],
                'location' => $validated['location'],
                'note' => $validated['note'],
                'is_available' => $validated['is_available'],
                'raw' => $row,
            ];
        }

        $this->showPreview = true;
    }

    public function confirmImport(): void
    {
        $rows = array_map(fn ($r) => $r['raw'], $this->validRows);

        $importer = new PlanEntryImport($rows);
        $importer->import();

        Flux::toast(
            heading: 'Import Complete',
            text: count($this->validRows).' entries imported successfully.',
            variant: 'success'
        );

        $this->reset(['file', 'validRows', 'errorRows', 'showPreview']);
    }

    public function cancelImport(): void
    {
        $this->reset(['file', 'validRows', 'errorRows', 'showPreview']);
    }

    public function isEmailNotFoundError(string $error): bool
    {
        return $error === 'The selected email is invalid.';
    }

    public function openCreateUserModal(int $rowIndex, string $email): void
    {
        $this->creatingForRowIndex = $rowIndex;
        $this->newUserEmail = strtolower($email);
        $this->newUserForenames = '';
        $this->newUserSurname = '';
        $this->newUserUsername = '';
        $this->newUserDefaultLocationId = null;
        $this->newUserDefaultCategory = '';
        $this->newUserTeamId = auth()->user()->managedTeams()->orderBy('name')->first()?->id;
        Flux::modal('create-user')->show();
    }

    public function saveNewUser(): void
    {
        $validated = $this->validate([
            'newUserForenames' => 'required|string|max:255',
            'newUserSurname' => 'required|string|max:255',
            'newUserEmail' => 'required|email|unique:users,email',
            'newUserUsername' => 'required|string|max:255|unique:users,username',
            'newUserDefaultLocationId' => 'nullable|integer|exists:locations,id',
            'newUserDefaultCategory' => 'nullable|string|max:255',
            'newUserTeamId' => 'required|exists:teams,id',
        ], [
            'newUserForenames.required' => 'Forenames is required.',
            'newUserSurname.required' => 'Surname is required.',
            'newUserEmail.required' => 'Email is required.',
            'newUserEmail.unique' => 'This email already exists.',
            'newUserUsername.required' => 'Username is required.',
            'newUserUsername.unique' => 'This username already exists.',
            'newUserTeamId.required' => 'Please select a team.',
        ]);

        $user = User::create([
            'forenames' => $validated['newUserForenames'],
            'surname' => $validated['newUserSurname'],
            'email' => strtolower($validated['newUserEmail']),
            'username' => $validated['newUserUsername'],
            'default_location_id' => $validated['newUserDefaultLocationId'],
            'default_category' => $validated['newUserDefaultCategory'] ?? '',
            'password' => Hash::make(Str::random(64)),
            'is_staff' => true,
            'is_admin' => false,
        ]);

        Team::find($validated['newUserTeamId'])->users()->attach($user);

        Flux::modal('create-user')->close();

        Flux::toast(
            heading: 'User Created',
            text: $user->full_name.' has been created and added to the team.',
            variant: 'success'
        );

        $this->parseFile();
    }

    public function render()
    {
        return view('livewire.import-plan-entries', [
            'managerTeams' => auth()->user()->managedTeams()->orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }
}
