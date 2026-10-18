<div>
    <x-ui.page-header
        :crumbs="['Administration', 'Personnel & access' => route('personnel.index'), $person ? $person->name : 'Add personnel']"
        :title="$person ? $person->name : 'Add personnel'"
        :description="$person ? null : 'The person receives an email link to set their own password. Administrators never see or send passwords.'">
        @if ($person)
            <x-slot:meta>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-[12.5px] text-slate">
                    @if ($person->is_active)
                        <span class="chip chip-done"><x-lucide-check class="size-3" /> Active</span>
                    @else
                        <span class="chip chip-neutral"><x-lucide-ban class="size-3" /> Deactivated {{ ops_format($person->deactivated_at, 'd M Y') }}</span>
                    @endif
                    <span>Account created {{ ops_format($person->created_at, 'd M Y') }}</span>
                    <span>· Last sign-in {{ $person->last_login_at ? ops_datetime($person->last_login_at) : 'never' }}</span>
                </div>
            </x-slot:meta>
        @endif
    </x-ui.page-header>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
        <form wire:submit="save" class="panel" novalidate>
            <div class="panel-header"><h2 class="section-title">Personnel details</h2></div>
            <div class="grid gap-4 p-4 md:grid-cols-2">
                <div>
                    <label for="name" class="field-label">Full name</label>
                    <input id="name" type="text" wire:model="name" class="input" autocomplete="off" @error('name') aria-invalid="true" @enderror>
                    <x-ui.error for="name" />
                </div>
                <div>
                    <label for="email" class="field-label">Work email</label>
                    <input id="email" type="email" wire:model="email" class="input" autocomplete="off" @error('email') aria-invalid="true" @enderror>
                    <x-ui.error for="email" />
                </div>
                <div>
                    <label for="staff_number" class="field-label">Staff number <span class="font-normal text-slate">— optional</span></label>
                    <input id="staff_number" type="text" wire:model="staff_number" class="input font-mono" placeholder="e.g. NP-804">
                    <x-ui.error for="staff_number" />
                </div>
                <div>
                    <label for="designation" class="field-label">Designation <span class="font-normal text-slate">— optional</span></label>
                    <input id="designation" type="text" wire:model="designation" class="input" placeholder="e.g. Senior Operations Analyst">
                    <x-ui.error for="designation" />
                </div>
                <div class="md:col-span-2">
                    <label for="team" class="field-label">Team <span class="font-normal text-slate">— optional</span></label>
                    <input id="team" type="text" wire:model="team" class="input">
                </div>
                <fieldset class="md:col-span-2">
                    <legend class="field-label">Role</legend>
                    <div class="grid gap-2 md:grid-cols-3">
                        @foreach ($roles as $r)
                            <label class="flex cursor-pointer gap-2.5 rounded-md border border-hairline p-3 hover:border-slate has-[:checked]:border-forest has-[:checked]:bg-forest-tint has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-forest">
                                <input type="radio" wire:model="role" value="{{ $r->value }}" class="mt-0.5 accent-forest">
                                <span>
                                    <span class="block text-[13px] font-medium">{{ $r->label() }}</span>
                                    <span class="mt-0.5 block text-[12px] leading-snug text-slate">{{ $r->description() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-ui.error for="role" />
                </fieldset>
            </div>
            <div class="flex flex-wrap items-center gap-2 border-t border-hairline px-4 py-3">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                    <x-lucide-check class="size-4" /> {{ $person ? 'Save changes' : 'Create account and send invitation' }}
                </button>
                <a href="{{ route('personnel.index') }}" class="btn btn-ghost">Back to personnel</a>
            </div>
        </form>

        @if ($person)
            <aside class="space-y-4">
                <section class="panel p-4">
                    <h2 class="section-title">Access</h2>
                    <div class="mt-3 space-y-2">
                        <button type="button" wire:click="sendResetLink" wire:confirm="Email a password reset link to {{ $person->email }}?" class="btn btn-secondary w-full justify-start" @disabled(! $person->is_active)>
                            <x-lucide-mail class="size-4" /> Send password reset link
                        </button>
                        @can('changeStatus', $person)
                            @if ($person->is_active)
                                <button type="button" wire:click="setActive(false)" wire:confirm="Deactivate {{ $person->name }}? They will be signed out and unable to sign in. Their updates and history are kept." class="btn btn-danger-ghost w-full justify-start">
                                    <x-lucide-user-x class="size-4" /> Deactivate account
                                </button>
                            @else
                                <button type="button" wire:click="setActive(true)" class="btn btn-secondary w-full justify-start">
                                    <x-lucide-user-check class="size-4" /> Reactivate account
                                </button>
                            @endif
                        @else
                            <p class="text-[12px] text-slate">You cannot deactivate your own account.</p>
                        @endcan
                        <x-ui.error for="status" />
                    </div>
                </section>

                <section class="panel p-4">
                    <h2 class="section-title">Recent activity updates</h2>
                    @forelse ($recentUpdates as $update)
                        <div class="mt-3 text-[12.5px]">
                            <p class="flex justify-between gap-2"><a href="{{ route('activities.show', $update->activity_instance_id) }}" class="truncate font-medium hover:underline">{{ $update->instance->definition->title }}</a><x-ui.status :state="$update->new_status" class="shrink-0" /></p>
                            <p class="font-mono text-[11.5px] text-slate">{{ ops_datetime($update->created_at) }}</p>
                        </div>
                    @empty
                        <p class="mt-2 text-[12.5px] text-slate">No updates recorded by this person.</p>
                    @endforelse
                    <a href="{{ route('reports.show', ['type' => 'personnel', 'user' => $person->id, 'from' => \App\Support\OpsClock::today()->subDays(29)->format('Y-m-d'), 'to' => \App\Support\OpsClock::today()->format('Y-m-d')]) }}" class="mt-3 inline-block text-[12px] font-medium text-forest hover:underline">30-day personnel report →</a>
                </section>

                <section class="panel p-4">
                    <h2 class="section-title">Account history</h2>
                    <ol class="mt-2 space-y-2">
                        @forelse ($accountEvents->concat($recentEvents)->unique('id')->sortByDesc('id')->take(8) as $event)
                            <li class="text-[12.5px]"><span class="font-mono text-[11.5px] text-slate">{{ ops_datetime($event->created_at) }}</span><br>{{ $event->description }} <span class="text-slate">· {{ $event->actor_name }}</span></li>
                        @empty
                            <li class="text-[12.5px] text-slate">No audit events yet.</li>
                        @endforelse
                    </ol>
                </section>
            </aside>
        @endif
    </div>
</div>
