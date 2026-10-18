<x-layouts.guest title="Reset your password">
    <a href="{{ route('login') }}" class="mb-6 inline-flex items-center gap-1 text-[12.5px] font-medium text-slate hover:text-ink"><x-lucide-chevron-left class="size-4" /> Back to sign in</a>
    <h2 class="text-[22px] font-semibold tracking-tight">Reset your password</h2>
    <p class="mt-1 text-[13.5px] text-slate">Enter your work email and we will send a link to choose a new password.</p>

    @if (session('status'))
        <p class="mt-5 flex items-start gap-2 rounded-md border border-forest-line bg-forest-tint p-3 text-[13px] text-forest" role="status">
            <x-lucide-mail class="mt-0.5 size-4 shrink-0" /> {{ session('status') }}
        </p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <div>
            <label for="email" class="field-label">Work email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" class="input h-10" autocomplete="username" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            <x-ui.error for="email" />
        </div>
        <button type="submit" class="btn btn-primary h-10 w-full"><x-lucide-send class="size-4" /> Send reset link</button>
    </form>
</x-layouts.guest>
