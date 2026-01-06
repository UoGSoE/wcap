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

    public ?int $deletingServiceId = null;

    public ?int $transferServiceId = null;

    public function mount(): void
    {
        if (! config('wcap.services_enabled')) {
            abort(404);
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
        $this->editingServiceId = null;
        $this->serviceName = '';
        $this->managerId = null;
        $this->selectedUserIds = [];
        Flux::modal('service-editor')->show();
    }

    public function editService(int $serviceId): void
    {
        $service = Service::with('users')->findOrFail($serviceId);

        $this->editingServiceId = $serviceId;
        $this->serviceName = $service->name;
        $this->managerId = $service->manager_id;
        $this->selectedUserIds = $service->users->pluck('id')->toArray();
        Flux::modal('service-editor')->show();
    }

    public function save(): void
    {
        $uniqueRule = $this->editingServiceId === null
            ? 'unique:services,name'
            : 'unique:services,name,'.$this->editingServiceId;

        $validated = $this->validate([
            'serviceName' => 'required|string|max:255|'.$uniqueRule,
            'managerId' => 'required|integer|exists:users,id',
            'selectedUserIds' => 'array',
            'selectedUserIds.*' => 'integer|exists:users,id',
        ]);

        $service = $this->editingServiceId
            ? Service::findOrFail($this->editingServiceId)
            : new Service;

        $service->fill([
            'name' => $validated['serviceName'],
            'manager_id' => $validated['managerId'],
        ])->save();

        $service->users()->sync($validated['selectedUserIds']);

        $action = $service->wasRecentlyCreated ? 'created' : 'updated';
        Flux::toast(
            heading: "Service {$action}!",
            text: "The service has been {$action} successfully.",
            variant: 'success'
        );

        Flux::modal('service-editor')->close();
        $this->editingServiceId = null;
        $this->serviceName = '';
        $this->managerId = null;
        $this->selectedUserIds = [];
    }

    public function confirmDelete(int $serviceId): void
    {
        $this->deletingServiceId = $serviceId;
        $this->transferServiceId = null;
        Flux::modal('service-delete')->show();
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

        Flux::modal('service-delete')->close();
        $this->deletingServiceId = null;
        $this->transferServiceId = null;
    }
}
