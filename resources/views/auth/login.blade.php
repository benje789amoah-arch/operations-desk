<x-layouts.guest title="Sign in">
    <h2 class="text-[22px] font-semibold tracking-tight">Sign in to the desk</h2>
    <p class="mt-1 text-[13.5px] text-slate">Use your work email. Accounts are created by an administrator.</p>

    @if (session('status'))
        <p class="mt-5 flex items-start gap-2 rounded-md border border-forest-line bg-forest-tint p-3 text-[13px] text-forest" role="status">
            <x-lucide-circle-check class="mt-0.5 size-4 shrink-0" /> {{ session('status') }}
        </p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4" x-data="{ submitting: false }" @submit="submitting = true" novalidate>
        @csrf
        <div>
            <label for="email" class="field-label">Work email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" class="input h-10" autocomplete="username" required autofocus
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            <x-ui.error for="email" />
        </div>
        <div x-data="{ show: false }">
            <div class="flex items-baseline justify-between">
                <label for="password" class="field-label">Password</label>
                <a href="{{ route('password.request') }}" class="text-[12.5px] font-medium text-forest hover:underline">Forgot password?</a>
            </div>
            <div class="relative">
                <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" class="input h-10 pr-10" autocomplete="current-password" required
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                <button type="button" class="absolute right-1.5 top-1.5 rounded p-1.5 text-slate hover:bg-subtle" @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'">
                    <x-lucide-eye class="size-4" x-show="!show" />
                    <x-lucide-eye class="size-4 opacity-40" x-show="show" x-cloak />
                </button>
            </div>
            <x-ui.error for="password" />
        </div>
        <label class="flex items-center gap-2 text-[13px]">
            <input type="checkbox" name="remember" value="1" class="checkbox" @checked(old('remember'))> Keep me signed in on this device
        </label>
        <button type="submit" class="btn btn-primary h-10 w-full" :disabled="submitting">
            <span x-show="!submitting" class="flex items-center gap-2"><x-lucide-lock class="size-4" /> Sign in</span>
            <span x-show="submitting" x-cloak class="flex items-center gap-2"><x-lucide-loader-circle class="size-4 animate-spin" /> Signing in…</span>
        </button>
    </form>

    <p class="mt-6 text-[12px] leading-relaxed text-slate">
        After {{ \App\Http\Requests\Auth\LoginRequest::MAX_ATTEMPTS }} failed attempts sign-in is paused for a minute. Sign-in attempts are recorded in the audit log.
    </p>

    @if (app()->environment('local') && \App\Models\User::query()->where('email', 'like', '%@example.com')->exists())
        <details class="mt-6 rounded-md border border-dashed border-amber-line bg-amber-tint/40 p-3 text-[12.5px]">
            <summary class="cursor-pointer font-medium text-amber">Local demo accounts</summary>
            <p class="mt-2 text-ink-soft">Demonstration data only (shown in the local environment). Password for all: <span class="font-mono">{{ \Database\Seeders\DemoDataSeeder::DEMO_PASSWORD }}</span></p>
            <ul class="mt-1.5 space-y-0.5 font-mono text-[11.5px]">
                <li>kofi.boateng@example.com — Administrator</li>
                <li>ama.mensah@example.com — Supervisor</li>
                <li>kwesi.appiah@example.com — Support</li>
            </ul>
        </details>
    @endif
</x-layouts.guest>
