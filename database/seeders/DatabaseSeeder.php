<?php

namespace Database\Seeders;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local development login. Never use these values outside local.
     */
    public const string LOCAL_ADMIN_EMAIL = 'admin@itera.test';

    public const string LOCAL_ADMIN_PASSWORD = 'password';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            $this->command?->warn('Skipped: the seeder adds development data only when APP_ENV=local.');

            return;
        }

        $this->seedLocalAdmin();
    }

    /**
     * Create the local admin account with a verified email and a personal team.
     */
    protected function seedLocalAdmin(): void
    {
        $admin = User::where('email', self::LOCAL_ADMIN_EMAIL)->first()
            ?? app(CreateNewUser::class)->create([
                'name' => 'Admin',
                'email' => self::LOCAL_ADMIN_EMAIL,
                'password' => self::LOCAL_ADMIN_PASSWORD,
                'password_confirmation' => self::LOCAL_ADMIN_PASSWORD,
            ]);

        if (! $admin->hasVerifiedEmail()) {
            $admin->markEmailAsVerified();
        }
    }
}
