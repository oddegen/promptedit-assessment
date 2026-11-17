<?php

namespace App\Console\Commands;

use App\Enums\CreditTransactionType;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('admin:create {email} {--name=Admin}')]
#[Description('Create an admin account, or reset one, and print its new password')]
class CreateAdminCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $email = (string) $this->argument('email');
        $password = Str::random(24);

        DB::transaction(function () use ($email, $password) {
            $user = User::firstOrNew(['email' => $email]);

            $user->forceFill([
                'name' => $user->name ?? (string) $this->option('name'),
                'password' => $password,
                'is_admin' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if ($user->wasRecentlyCreated) {
                $user->creditTransactions()->create([
                    'type' => CreditTransactionType::Bonus,
                    'amount' => Config::integer('credits.welcome_bonus'),
                ]);
            }
        });

        $this->components->twoColumnDetail('Email', $email);
        $this->components->twoColumnDetail('Password', $password);
        $this->components->warn('This password is only shown once.');
    }
}
