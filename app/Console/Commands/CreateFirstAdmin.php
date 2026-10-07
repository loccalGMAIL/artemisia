<?php

namespace App\Console\Commands;

use App\Actions\CreateAccountAction;
use App\Exceptions\DuplicateAccountEmailException;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Installation step: the first admin is created outside the web interface (spec 001, section 9).
 */
#[Signature('accounts:create-first-admin {name : Nombre completo} {email : Email de la cuenta}')]
#[Description('Create the first admin account on a fresh installation')]
class CreateFirstAdmin extends Command
{
    public function handle(CreateAccountAction $createAccount, RoleSeeder $roles): int
    {
        if (User::anyAdminExists()) {
            $this->error(__('auth.install.admin_exists'));

            return self::FAILURE;
        }

        $roles->run();

        try {
            $admin = $createAccount->handle([
                'name' => $this->argument('name'),
                'email' => $this->argument('email'),
                'role' => 'admin',
            ]);
        } catch (DuplicateAccountEmailException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(__('auth.install.created', ['email' => $admin->email]));

        return self::SUCCESS;
    }
}
