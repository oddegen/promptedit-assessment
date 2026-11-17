<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

it('creates a verified admin and prints its password', function () {
    Str::createRandomStringsUsing(fn () => 'a-fresh-password');

    $this->artisan('admin:create', ['email' => 'grader@example.com'])
        ->expectsOutputToContain('a-fresh-password')
        ->assertSuccessful();

    $admin = User::firstWhere('email', 'grader@example.com');
    expect($admin)->is_admin->toBeTrue()->hasVerifiedEmail()->toBeTrue()
        ->and(Hash::check('a-fresh-password', $admin->password))->toBeTrue();
});

it('promotes an existing account and replaces its password', function () {
    $user = User::factory()->create();
    Str::createRandomStringsUsing(fn () => 'a-fresh-password');

    $this->artisan('admin:create', ['email' => $user->email])->assertSuccessful();

    expect($user->refresh())->is_admin->toBeTrue()
        ->and(Hash::check('a-fresh-password', $user->password))->toBeTrue();
});
