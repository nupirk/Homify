<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates (or refreshes) the platform administrator.
 *
 * Credentials come from the environment so no password ever lives in git:
 *
 *   ADMIN_NAME="Super Admin"
 *   ADMIN_EMAIL=admin@example.com
 *   ADMIN_PHONE=+977-9800000000
 *   ADMIN_PASSWORD=            # leave empty to generate a strong one
 *
 * (Values are read through config/seed.php so they still apply when the config
 * is cached.)
 *
 * Safe to re-run: an existing admin keeps its current password. On a fresh
 * database this user is user_id 1, which is what PLATFORM_USER_ID expects for
 * the platform ledger account.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // config(), not env(): `config:cache` skips loading .env, and falling
        // back to the default email there would create or leave behind the
        // wrong admin account.
        $email = strtolower(trim((string) config('seed.admin.email')));
        $phone = trim((string) config('seed.admin.phone'));
        $name = trim((string) config('seed.admin.name'));
        $password = (string) config('seed.admin.password', '');

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('ADMIN_EMAIL is missing or not a valid email address.');
        }

        [$firstName, $lastName] = $this->splitName($name);

        // phone is unique across users too - a clear error beats a 500 from the
        // unique constraint when someone re-seeds with a different email.
        $phoneOwner = User::where('phone', $phone)->where('email', '!=', $email)->first();

        if ($phoneOwner) {
            throw new RuntimeException(
                "ADMIN_PHONE {$phone} already belongs to {$phoneOwner->email}. "
                . 'Pick a different phone or update that user first.'
            );
        }

        $admin = User::firstWhere('email', $email);
        $isNew = $admin === null;
        $generatedPassword = null;

        if ($isNew) {
            if ($password === '') {
                $generatedPassword = Str::password(20);
                $password = $generatedPassword;
            }

            $this->assertPasswordStrongEnough($password);

            $admin = new User();
            $admin->email = $email;
            $admin->password = Hash::make($password);
            $admin->email_verified_at = now();
            $admin->phone_verified_at = now();
        }

        $admin->first_name = $firstName;
        $admin->last_name = $lastName;
        $admin->phone = $phone;
        $admin->user_type = 'admin';
        $admin->is_active = true;
        $admin->save();

        $this->command->info("Admin ready: {$admin->email} (user_id {$admin->user_id})");

        if ($generatedPassword !== null) {
            $this->command->warn("Generated admin password (shown once): {$generatedPassword}");
        }

        if (! $isNew) {
            $this->command->line('Existing admin: password left untouched.');
        }

        $platformUserId = (int) config('payment.platform_user_id', 1);

        if ((int) $admin->user_id !== $platformUserId) {
            $this->command->warn(
                "This admin is user_id {$admin->user_id} but PLATFORM_USER_ID is "
                . $platformUserId . '. Set PLATFORM_USER_ID to match.'
            );
        }
    }

    private function assertPasswordStrongEnough(string $password): void
    {
        if (strlen($password) < 12) {
            throw new RuntimeException(
                'ADMIN_PASSWORD must be at least 12 characters, or leave it empty to generate one.'
            );
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return ['Super', 'Admin'];
        }

        $first = mb_substr(array_shift($parts), 0, 50);
        $last = mb_substr($parts === [] ? 'Admin' : implode(' ', $parts), 0, 50);

        return [$first, $last];
    }
}
