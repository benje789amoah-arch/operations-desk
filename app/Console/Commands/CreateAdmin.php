<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * The documented, secure way to create the first administrator in any
 * environment. The password is typed at a hidden prompt and never echoed,
 * logged or accepted as a command-line option.
 */
class CreateAdmin extends Command
{
    protected $signature = 'operations:create-admin {--name= : Full name} {--email= : Work email}';

    protected $description = 'Create an administrator account (interactive)';

    public function handle(AuditLogger $audit): int
    {
        $name = trim($this->option('name') ?: text('Full name', required: true));
        // Stored emails are lower case (see User::email()), so validate them that way.
        $email = Str::lower(trim($this->option('email') ?: text('Work email', required: true)));
        $secret = password('Password (min. 10 characters, mixed case and a number)', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $secret],
            [
                'name' => ['required', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = (new User)->forceFill([
            'name' => $name,
            'email' => $email,
            'role' => Role::Administrator,
            'designation' => 'Administrator',
            'password' => $secret,
            'password_changed_at' => now(),
            'is_active' => true,
        ]);
        $user->save();

        $audit->recordSystem('personnel.created', "Created administrator account for {$name} from the console", $user, $email);
        $this->info("Administrator {$email} created. Sign in at ".route('login'));

        return self::SUCCESS;
    }
}
