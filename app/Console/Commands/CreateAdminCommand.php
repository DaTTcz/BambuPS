<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature   = 'bambups:create-admin
                                {--name= : Jméno administrátora}
                                {--email= : E-mail administrátora}
                                {--password= : Heslo administrátora (pokud vynecháno, appka se zeptá interaktivně)}';

    protected $description = 'Vytvoří prvního administrátorského uživatele (pro čistou instalaci)';

    public function handle(): int
    {
        if (User::count() > 0) {
            $this->error('V databázi už existuje alespoň jeden uživatel. Tento příkaz je určen jen pro čistou instalaci.');
            $this->line('Pokud přesto chceš vytvořit dalšího uživatele, udělej to přes appku (Nastavení účtu) nebo php artisan tinker.');
            return self::FAILURE;
        }

        $name  = $this->option('name') ?: $this->ask('Jméno administrátora');
        $email = $this->option('email') ?: $this->ask('E-mail administrátora (bude sloužit k přihlášení)');

        $password = $this->option('password');
        if (!$password) {
            $password = $this->secret('Heslo (min. 8 znaků)');
            $confirm  = $this->secret('Zopakuj heslo');
            if ($password !== $confirm) {
                $this->error('Hesla se neshodují.');
                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $user = User::create([
            'name'              => $name,
            'email'             => $email,
            'password'          => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->newLine();
        $this->info("✅ Administrátor vytvořen: {$user->name} <{$user->email}>");
        $this->line('Teď se můžeš přihlásit v appce a nastavit zbytek (tiskárny, moduly, notifikace) přímo přes web.');

        return self::SUCCESS;
    }
}
