<?php

namespace App\Livewire\Activities;

use App\Actions\RecordActivityUpdate;
use App\Enums\ActivityStatus;
use App\Exceptions\StaleActivityUpdateException;
use App\Models\ActivityInstance;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Records a Done/Pending update. The updater is always the authenticated
 * user and the timestamp is set by the server; neither can be supplied by
 * the browser. "version" is the state the user was looking at, so a save
 * after someone else's update is rejected instead of silently overwriting.
 */
class RecordUpdateForm extends Component
{
    #[Locked]
    public int $instanceId;

    #[Locked]
    public int $version;

    public string $status = '';

    public string $remark = '';

    public string $supportingDetails = '';

    public bool $followUp = false;

    public ?string $conflict = null;

    public bool $compact = false;

    public function mount(ActivityInstance $instance, bool $compact = false): void
    {
        $this->authorize('view', $instance);

        $this->instanceId = $instance->id;
        $this->version = $instance->version;
        $this->compact = $compact;
    }

    #[Computed]
    public function instance(): ActivityInstance
    {
        return ActivityInstance::query()->withRowContext()->with('lastUpdater')->findOrFail($this->instanceId);
    }

    public function isReopening(): bool
    {
        return $this->instance->isDone() && $this->status === ActivityStatus::Pending->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ActivityStatus::class)],
            'remark' => ['required', 'string', 'min:'.config('operations.remark_min_length'), 'max:2000'],
            'supportingDetails' => ['nullable', 'string', 'max:500'],
            'followUp' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'status.required' => 'Choose whether the activity is Done or still Pending.',
            'remark.required' => $this->isReopening()
                ? 'Explain why this completed activity is being reopened.'
                : 'Add a remark so the next person on the desk knows what was found.',
            'remark.min' => 'Write at least :min characters so the remark is useful at handover.',
        ];
    }

    public function save(RecordActivityUpdate $recordUpdate): void
    {
        $instance = $this->instance;
        $this->authorize('recordUpdate', $instance);
        $this->validate();

        try {
            $update = $recordUpdate->handle(
                instance: $instance,
                user: auth()->user(),
                status: ActivityStatus::from($this->status),
                remark: $this->remark,
                expectedVersion: $this->version,
                supportingDetails: $this->supportingDetails,
                requiresFollowUp: $this->followUp,
            );
        } catch (StaleActivityUpdateException $exception) {
            $this->conflict = $exception->getMessage();
            unset($this->instance);

            return;
        }

        $this->reset(['status', 'remark', 'supportingDetails', 'followUp', 'conflict']);
        $this->version++;
        unset($this->instance);

        $this->dispatch('activity-updated', instanceId: $this->instanceId);
        $this->dispatch('toast', message: sprintf('%s recorded as %s at %s.', $instance->definition->code, $update->new_status->label(), ops_time($update->created_at)), tone: $update->new_status === ActivityStatus::Done ? 'positive' : 'attention');
    }

    /**
     * After reviewing a conflicting update, the user may resubmit against
     * the latest version. Their typed remark is kept.
     */
    public function acceptLatest(): void
    {
        $this->version = $this->instance->version;
        $this->conflict = null;
    }

    public function cancel(): void
    {
        $this->reset(['status', 'remark', 'supportingDetails', 'followUp']);
        $this->resetValidation();
        $this->dispatch('update-form-cancelled');
    }

    public function render()
    {
        $permission = Gate::inspect('recordUpdate', $this->instance);

        return view('livewire.activities.record-update-form', [
            'canRecord' => $permission->allowed(),
            'denial' => $permission->message(),
        ]);
    }
}
