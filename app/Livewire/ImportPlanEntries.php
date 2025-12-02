<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\PlanEntryImport;
use App\Services\PlanEntryRowValidator;
use Flux\Flux;
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

    public function mount(): void
    {
        if (! auth()->user()->isManager()) {
            abort(403, 'You do not manage any teams.');
        }
    }

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
                if ($index === 0) {
                    continue;
                }

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

    public function render()
    {
        return view('livewire.import-plan-entries');
    }
}
