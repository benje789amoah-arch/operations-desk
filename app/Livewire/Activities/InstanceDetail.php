<?php

namespace App\Livewire\Activities;

use App\Actions\AssignActivityInstance;
use App\Models\ActivityInstance;
use App\Models\User;
use App\Support\InstanceTimeline;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Full record of one daily activity instance: identity, procedure, the
 * update form and its complete audit timeline.
 */
class InstanceDetail extends Component
{
    #[Locked]
    public int $instanceId;

    public string $assignee = '';

    public function mount(ActivityInstance $instance): void
    {
        $this->authorize('view', $instance);

        $this->instanceId = $instance->id;
        $this->assignee = (string) ($instance->assigned_to ?? '');
    }

    public function instance(): ActivityInstance
    {
        return ActivityInstance::query()
            ->withRowContext()
            ->with(['definition.defaultAssignee', 'creator', 'updates'])
            ->findOrFail($this->instanceId);
    }

    #[On('activity-updated')]
    public function refreshTimeline(): void
    {
        // Re-render pulls the fresh instance and timeline.
    }

    public function reassign(AssignActivityInstance $action): void
    {
        $instance = $this->instance();
        $this->authorize('assign', $instance);

        $this->validate(['assignee' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)]]);

        $action->handle($instance, $this->assignee ? User::find($this->assignee) : null, auth()->user());

        $this->dispatch('toast', message: 'Responsible person updated.');
    }

    public function render()
    {
        $instance = $this->instance();

        return view('livewire.activities.instance-detail', [
            'instance' => $instance,
            'timeline' => InstanceTimeline::for($instance),
            'people' => auth()->user()->can('assign', $instance) ? User::query()->active()->orderBy('name')->get(['id', 'name']) : collect(),
        ])->title($instance->definition->code.' · '.$instance->operational_date->format('d M Y'));
    }
}
