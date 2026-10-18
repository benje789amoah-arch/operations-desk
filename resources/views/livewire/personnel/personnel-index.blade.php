<div>
    <x-ui.page-header
        :crumbs="['Administration', 'Personnel & access']"
        title="Personnel & access"
        description="Accounts for everyone who works the desk. Only what operational accountability needs is stored: name, work email, staff number, designation, team and role.">
        <x-slot:actions>
            <a href="{{ route('personnel.create') }}" class="btn btn-primary"><x-lucide-user-plus class="size-4" /> Add personnel</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat label="Active accounts" :value="$activeCount" icon="user-check" :hint="$inactiveCount.' deactivated (history kept)'" />
        @foreach ($roles as $r)
            <x-ui.stat :label="$r->label()" :value="$roleCounts[$r->value] ?? 0" :icon="['administrator' => 'shield-check', 'supervisor' => 'badge-check', 'support' => 'user'][$r->value]" hint="Active accounts" />
        @endforeach
    </div>

    <section class="panel mb-4 overflow-hidden">
        <div class="flex flex-col gap-2 border-b border-hairline p-2.5 md:flex-row md:items-center">
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-slate" />
                <label for="people-search" class="sr-only">Search personnel</label>
                <input id="people-search" type="search" wire:model.live.debounce.350ms="search" class="input pl-8" placeholder="Search name, email or staff number…">
            </div>
            <div class="grid grid-cols-2 gap-2 md:flex">
                <label for="people-role" class="sr-only">Role</label>
                <select id="people-role" wire:model.live="role" class="input md:w-48">
                    <option value="">All roles</option>
                    @foreach ($roles as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
                </select>
                <label for="people-status" class="sr-only">Account status</label>
                <select id="people-status" wire:model.live="status" class="input md:w-40">
                    <option value="active">Active</option>
                    <option value="inactive">Deactivated</option>
                    <option value="all">All accounts</option>
                </select>
            </div>
        </div>

        @if ($people->isEmpty())
            <x-ui.empty icon="users" title="No personnel match these filters" />
        @else
            <div class="overflow-x-auto">
                <table class="table-ops">
                    <thead><tr><th>Person</th><th>Role</th><th class="hidden md:table-cell">Designation · team</th><th class="hidden lg:table-cell text-right">Updates (30 days)</th><th class="hidden lg:table-cell">Last sign-in</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($people as $person)
                            <tr wire:key="person-{{ $person->id }}">
                                <td>
                                    <span class="flex items-center gap-2.5">
                                        <x-ui.avatar :name="$person->name" />
                                        <span class="min-w-0">
                                            <a href="{{ route('personnel.edit', $person) }}" class="font-medium hover:underline">{{ $person->name }}</a>
                                            <span class="block truncate text-[11.5px] text-slate"><span class="font-mono">{{ $person->staff_number ?? '—' }}</span> · {{ $person->email }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td><span @class(['chip', 'chip-ink' => $person->role->value === 'administrator', 'chip-done' => $person->role->value === 'supervisor', 'chip-neutral' => $person->role->value === 'support'])>{{ $person->role->label() }}</span></td>
                                <td class="hidden text-[12.5px] md:table-cell">{{ $person->designation ?? '—' }}<span class="block text-[11.5px] text-slate">{{ $person->team }}</span></td>
                                <td class="num hidden text-right font-mono lg:table-cell">{{ $person->updates_30d }}</td>
                                <td class="hidden whitespace-nowrap font-mono text-[12px] text-slate lg:table-cell">{{ $person->last_login_at ? ops_datetime($person->last_login_at) : 'Never' }}</td>
                                <td>
                                    @if ($person->is_active)
                                        <span class="chip chip-done"><x-lucide-check class="size-3" /> Active</span>
                                    @else
                                        <span class="chip chip-neutral"><x-lucide-ban class="size-3" /> Deactivated</span>
                                    @endif
                                </td>
                                <td class="text-right"><a href="{{ route('personnel.edit', $person) }}" class="btn btn-ghost btn-sm">Manage <x-lucide-arrow-right class="size-3.5" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $people->links() }}
        @endif
    </section>

    {{-- The permission matrix rendered from App\Support\Ability, the same source the server enforces. --}}
    <section class="panel overflow-hidden" aria-labelledby="matrix-title">
        <div class="panel-header">
            <h2 id="matrix-title" class="section-title flex items-center gap-2"><x-lucide-shield-check class="size-4 text-slate" /> Role permissions</h2>
            <p class="meta">Enforced on the server for every request</p>
        </div>
        <div class="overflow-x-auto">
            <table class="table-ops">
                <thead><tr><th>Capability</th>@foreach ($roles as $r)<th class="text-center">{{ $r->label() }}</th>@endforeach</tr></thead>
                <tbody>
                    <tr><td class="text-[12.5px]">View dashboard, daily register, handover and activity history</td>@foreach ($roles as $r)<td class="text-center"><x-lucide-check class="mx-auto size-4 text-forest" aria-label="Allowed" /></td>@endforeach</tr>
                    @foreach ($matrix as $ability => $definition)
                        <tr>
                            <td class="text-[12.5px]">{{ $definition['label'] }}</td>
                            @foreach ($roles as $r)
                                <td class="text-center">
                                    @if (in_array($r, $definition['roles'], true))
                                        <x-lucide-check class="mx-auto size-4 text-forest" aria-label="Allowed" />
                                    @else
                                        <x-lucide-minus class="mx-auto size-4 text-rule" aria-label="Not allowed" />
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
