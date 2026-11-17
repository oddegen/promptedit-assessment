<?php

use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-10 12:00:00');
    $this->admin = User::factory()->admin()->create();
    Generation::factory()->succeeded()->create(['completed_at' => '2026-11-09 08:00:00']);
    Generation::factory()->succeeded()->create(['completed_at' => '2026-11-09 09:30:00']);
    Generation::factory()->failed()->create(['completed_at' => '2026-11-09 10:00:00']);
});

it('keeps the report to admins', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('admin.report'));

    $response->assertForbidden();
});

it('adds up what was charged, what the model cost and the margin', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/usage*' => Http::response(['data' => []])]);

    $response = $this->actingAs($this->admin)->get(route('admin.report'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/Report')
        ->where('summary.generations', 2)
        ->where('summary.charged', 763_775_000)
        ->where('summary.cost', 611_020_000)
        ->where('summary.profit', 152_755_000)
        ->where('summary.margin', '20.0')
        ->has('days', 30)
        ->where('days.1.date', '2026-11-09')
        ->where('days.1.charged', 763_775_000)
        ->has('users', 2));
});

it('matches each day against what Pixelforge billed', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/usage*' => Http::response(['data' => [
        ['date' => '2026-11-09', 'generations' => 2, 'cost_usd' => '0.611020000'],
        ['date' => '2026-11-08', 'generations' => 1, 'cost_usd' => '0.010000000'],
    ]])]);

    $response = $this->actingAs($this->admin)->get(route('admin.report'));

    $response->assertInertia(fn (Assert $page) => $page
        ->missing('provider')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('provider.available', true)
            ->where('provider.days.2026-11-09.status', 'matched')
            ->where('provider.days.2026-11-08.status', 'differs')
            ->where('provider.days.2026-11-08.difference', -10_000_000)
            ->where('provider.days.2026-11-10.status', 'in_progress')));
});

it('still shows our numbers when Pixelforge cannot be reached', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/usage*' => Http::failedConnection()]);

    $response = $this->actingAs($this->admin)->get(route('admin.report'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('summary.generations', 2)
        ->loadDeferredProps(fn (Assert $reload) => $reload->where('provider.available', false)));
});
