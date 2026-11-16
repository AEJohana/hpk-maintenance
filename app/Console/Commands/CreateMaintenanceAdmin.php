<?php

namespace App\Console\Commands;

use App\Models\AccessRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateMaintenanceAdmin extends Command
{
    protected $signature = 'maintenance:admin {email} {--name=Administrator}';

    protected $description = 'Buat akun administrator maintenance pertama';

    public function handle(): int
    {
        if (! filter_var($this->argument('email'), FILTER_VALIDATE_EMAIL)) {
            $this->error('Email tidak valid.');

            return 1;
        }
        if (User::where('email', $this->argument('email'))->exists()) {
            $this->error('Email sudah terdaftar.');

            return 1;
        }
        $password = $this->secret('Kata sandi (minimal 10 karakter)');
        if (strlen($password ?? '') < 10) {
            $this->error('Kata sandi terlalu pendek.');

            return 1;
        }
        User::create(['name' => $this->option('name'), 'email' => $this->argument('email'), 'password' => Hash::make($password), 'is_admin' => true, 'role' => 'admin', 'role_id' => AccessRole::where('type', 'admin')->firstOrFail()->id, 'active' => true]);
        $this->info('Administrator berhasil dibuat.');

        return 0;
    }
}
