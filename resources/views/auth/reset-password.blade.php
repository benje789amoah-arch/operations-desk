<x-layouts.guest title="Choose a new password">
    <h2 class="text-[22px] font-semibold tracking-tight">Choose a new password</h2>
    <p class="mt-1 text-[13.5px] text-slate">At least 10 characters with upper and lower case letters and a number.</p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="field-label">Work email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" class="input h-10" autocomplete="username" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            <x-ui.error for="email" />
        </div>
        <div>
            <label for="password" class="field-label">New password</label>
            <input id="password" name="password" type="password" class="input h-10" autocomplete="new-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            <x-ui.error for="password" />
        </div>
        <div>
            <label for="password_confirmation" class="field-label">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input h-10" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary h-10 w-full"><x-lucide-key-round class="size-4" /> Save password</button>
    </form>
</x-layouts.guest>
