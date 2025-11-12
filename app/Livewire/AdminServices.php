<?php

namespace App\Livewire;

use App\Models\Service;
use App\Models\User;
use Flux\Flux;
use Livewire\Component;

class AdminServices extends Component
{
    public ?int $editingServiceId = null;

    public string $serviceName = '';

    public ?int $managerId = null;

    public array $selectedUserIds = [];

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    public ?int $deletingServiceId = null;

    public ?int $transferServiceId = null;

    public function mount(): void
    {
        if (! config('wcap.services_enabled')) {
            abort(404);
        }

        $user = auth()->user();

        if (! $user->isAdmin()) {
            abort(403, 'You must be an admin to access this page.');
        }
    }

    public function render()
    {
        $services = Service::with(['manager', 'users'])->orderBy('name')->get();
        $users = User::orderBy('surname')->orderBy('forenames')->get();

        return view('livewire.admin-services', [
            'services' => $services,
            'users' => $users,
        ]);
    }

    public function createService(): void
    {
        $this->editingServiceId = -1;
        $this->serviceName = '';
        $this->managerId = null;
        $this->selectedUserIds = [];
        $this->showEditModal = true;
    }

    public function editService(int $serviceId): void
    {
        $service = Service::with('users')->findOrFail($serviceId);

        $this->editingServiceId = $serviceId;
        $this->serviceName = $service->name;
        $this->managerId = $service->manager_id;
        $this->selectedUserIds = $service->users->pluck('id')->toArray();
        $this->showEditModal = true;
    }

    public function save(): void
    {
        $uniqueRule = $this->editingServiceId === -1
            ? 'unique:services,name'
            : 'unique:services,name,'.$this->editingServiceId;

        $validated = $this->validate([
            'serviceName' => 'required|string|max:255|'.$uniqueRule,
            'managerId' => 'required|integer|exists:users,id',
            'selectedUserIds' => 'array',
            'selectedUserIds.*' => 'integer|exists:users,id',
        ], [
            'serviceName.required' => 'Service name is required.',
            'serviceName.unique' => 'A service with this name already exists.',
            'managerId.required' => 'Manager is required.',
        ]);

        if ($this->editingServiceId === -1) {
            $service = Service::create([
                'name' => $validated['serviceName'],
                'manager_id' => $validated['managerId'],
            ]);

            Flux::toast(
                heading: 'Service created!',
                text: 'The service has been created successfully.',
                variant: 'success'
            );
        } else {
            $service = Service::findOrFail($this->editingServiceId);
            $service->update([
                'name' => $validated['serviceName'],
                'manager_id' => $validated['managerId'],
            ]);

            Flux::toast(
                heading: 'Service updated!',
                text: 'The service has been updated successfully.',
                variant: 'success'
            );
        }

        $service->users()->sync($validated['selectedUserIds']);

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->showEditModal = false;
        $this->editingServiceId = null;
        $this->serviceName = '';
        $this->managerId = null;
        $this->selectedUserIds = [];
    }

    public function confirmDelete(int $serviceId): void
    {
        $this->deletingServiceId = $serviceId;
        $this->transferServiceId = null;
        $this->showDeleteModal = true;
    }

    public function deleteService(): void
    {
        $validated = $this->validate([
            'transferServiceId' => 'nullable|integer|exists:services,id',
        ]);

        $service = Service::with('users')->findOrFail($this->deletingServiceId);

        if ($validated['transferServiceId']) {
            $transferService = Service::findOrFail($validated['transferServiceId']);
            $memberIds = $service->users->pluck('id')->toArray();
            $transferService->users()->syncWithoutDetaching($memberIds);
        }

        $service->users()->detach();
        $service->delete();

        Flux::toast(
            heading: 'Service deleted!',
            text: 'The service has been deleted successfully.',
            variant: 'success'
        );

        $this->showDeleteModal = false;
        $this->deletingServiceId = null;
        $this->transferServiceId = null;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingServiceId = null;
        $this->transferServiceId = null;
    }
}
