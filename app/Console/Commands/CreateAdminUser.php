<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin {username?} {--password=}';

    protected $description = 'Create the first Kankio admin user without relying on default credentials.';

    public function handle(): int
    {
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $this->argument('username') ?: $this->ask('Admin kullanıcı adı')));
        $password = (string) ($this->option('password') ?: $this->secret('Admin şifresi'));

        if (strlen($username) < 3 || strlen($password) < 8) {
            throw ValidationException::withMessages([
                'admin' => 'Kullanıcı adı en az 3, şifre en az 8 karakter olmalıdır.',
            ]);
        }

        $email = $username.'@kank.com';

        if (User::where('username', $username)->orWhere('email', $email)->exists()) {
            $this->error('Bu admin kullanıcı adı zaten kullanılıyor.');
            return self::FAILURE;
        }

        User::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->info("Admin oluşturuldu: {$username}");

        return self::SUCCESS;
    }
}
