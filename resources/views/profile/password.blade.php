<x-layouts.app title="Change password">
    <x-ui.page-header :crumbs="['Account', 'My profile' => route('profile.show'), 'Change password']" title="Change password" description="Changing your password signs you out of every other browser or device." />

    <form method="POST" action="{{ route('password.update') }}" class="panel max-w-xl" x-data="{ submitting: false }" @submit="submitting = true" novalidate>
        @csrf
        @method('PUT')
        <div class="space-y-4 p-5">
            <div>
                <label for="current_password" class="field-label">Current password</label>
                <input id="current_password" name="current_password" type="password" class="input" autocomplete="current-password" required @error('current_password', 'updatePassword') aria-invalid="true" aria-describedby="current_password-error" @enderror>
                <x-ui.error for="current_password" bag="updatePassword" />
            </div>
            <div>
                <label for="password" class="field-label">New password</label>
                <input id="password" name="password" type="password" class="input" autocomplete="new-password" required @error('password', 'updatePassword') aria-invalid="true" aria-describedby="password-error" @enderror>
                <p class="field-hint">At least 10 characters, with upper and lower case letters and a number.</p>
                <x-ui.error for="password" bag="updatePassword" />
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
            </div>
        </div>
        <div class="flex gap-2 border-t border-hairline px-5 py-3">
            <button type="submit" class="btn btn-primary" :disabled="submitting"><x-lucide-key-round class="size-4" /> Update password</button>
            <a href="{{ route('profile.show') }}" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</x-layouts.app>
