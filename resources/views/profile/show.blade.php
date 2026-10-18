<x-layouts.app title="My profile">
    <x-ui.page-header :crumbs="['Account', 'My profile']" title="My profile" description="The details recorded against every update you make. Ask an administrator if any of them need correcting.">
        <x-slot:actions>
            <a href="{{ route('password.edit') }}" class="btn btn-secondary"><x-lucide-key-round class="size-4" /> Change password</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-4">
            <section class="panel p-5">
                <div class="flex items-center gap-4">
                    <x-ui.avatar :name="$user->name" size="lg" />
                    <div>
                        <p class="text-[17px] font-semibold">{{ $user->name }}</p>
                        <p class="text-[13px] text-slate">{{ $user->designation ?? 'No designation recorded' }}</p>
                    </div>
                </div>
                <dl class="mt-5 grid gap-x-6 gap-y-3 text-[13px] sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="meta">Work email</dt><dd class="mt-0.5">{{ $user->email }}</dd></div>
                    <div><dt class="meta">Staff number</dt><dd class="mt-0.5 font-mono">{{ $user->staff_number ?? '—' }}</dd></div>
                    <div><dt class="meta">Role</dt><dd class="mt-0.5">{{ $user->role->label() }}</dd></div>
                    <div><dt class="meta">Team</dt><dd class="mt-0.5">{{ $user->team ?? '—' }}</dd></div>
                    <div><dt class="meta">Password last changed</dt><dd class="mt-0.5">{{ $user->password_changed_at ? ops_datetime($user->password_changed_at) : 'Not recorded' }}</dd></div>
                    <div><dt class="meta">Updates this month</dt><dd class="num mt-0.5 font-mono">{{ $updatesThisMonth }}</dd></div>
                </dl>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="section-title">Your recent updates</h2>
                    <a href="{{ route('reports.show', ['type' => 'personnel', 'from' => \App\Support\OpsClock::today()->subDays(29)->format('Y-m-d'), 'to' => \App\Support\OpsClock::today()->format('Y-m-d')]) }}" class="text-[12px] font-medium text-forest hover:underline">My 30-day report →</a>
                </div>
                @if ($recentUpdates->isEmpty())
                    <x-ui.empty icon="history" title="You have not recorded any updates yet" />
                @else
                    <ul class="divide-y divide-hairline">
                        @foreach ($recentUpdates as $update)
                            <li class="flex items-start justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('activities.show', $update->activity_instance_id) }}" class="font-medium hover:underline">{{ $update->instance->definition->title }}</a>
                                    <p class="line-clamp-1 text-[12.5px] text-slate">{{ $update->remark }}</p>
                                </div>
                                <div class="shrink-0 text-right"><x-ui.status :state="$update->new_status" /><p class="mt-1 font-mono text-[11.5px] text-slate">{{ ops_format($update->created_at, 'd M H:i') }}</p></div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <section class="panel p-4">
            <h2 class="section-title">What your role can do</h2>
            <ul class="mt-3 space-y-2 text-[13px]">
                <li class="flex gap-2"><x-lucide-check class="mt-0.5 size-4 shrink-0 text-forest" /> View the dashboard, register, handovers and activity history</li>
                @foreach ($abilities as $ability)
                    <li class="flex gap-2"><x-lucide-check class="mt-0.5 size-4 shrink-0 text-forest" /> {{ $ability['label'] }}</li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.app>
