<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AdminCreate extends Command
{
    protected $signature = 'admin:create {email? : Yönetici e-posta adresi} {--name=Admin : Görünen ad}';

    protected $description = 'Güçlü parola ile bir yönetici (admin) hesabı oluşturur';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?: $this->ask('Yönetici e-posta adresi'))));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Geçersiz e-posta adresi.');
            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('Bu e-posta ile bir kullanıcı zaten mevcut.');
            return self::FAILURE;
        }

        $password = $this->secret('Parola (en az 12 karakter, gizli girilir)');
        $confirmation = $this->secret('Parolayı tekrar girin');

        $validator = validator(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $err) {
                $this->error($err);
            }
            return self::FAILURE;
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = new User();
        $user->name = (string) $this->option('name');
        $user->email = $email;
        $user->username = 'admin_'.strtolower(Str::random(6));
        $user->password = Hash::make($password);
        $user->is_verified = true;
        $user->email_verified_at = now();
        $user->save();
        $user->assignRole('admin');

        $this->info("Yönetici oluşturuldu: {$email}");
        return self::SUCCESS;
    }
}
