<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
                            {--name= : The administrator name}
                            {--email= : The administrator email address}';

    protected $description = 'Create an administrator account interactively';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Administrator name')));
        $email = trim((string) ($this->option('email') ?: $this->ask('Administrator email')));

        $validator = Validator::make(
            compact('name', 'email'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (minimum 8 characters)');
        $passwordConfirmation = (string) $this->secret('Confirm password');

        if (strlen($password) < 8) {
            $this->error('The password must be at least 8 characters.');

            return self::FAILURE;
        }

        if (! hash_equals($password, $passwordConfirmation)) {
            $this->error('The password confirmation does not match.');

            return self::FAILURE;
        }

        $admin = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->info("Administrator account created: {$admin->email}");

        return self::SUCCESS;
    }
}
