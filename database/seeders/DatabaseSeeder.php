<?php

namespace Database\Seeders;

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use App\Models\Generation;
use App\Models\User;
use App\Services\Markup;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\TokenUsage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(PixelforgeClient $pixelforge, Markup $markup): void
    {
        // User::factory(10)->create();

        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->creditTransactions()->create([
            'type' => CreditTransactionType::Bonus,
            'amount' => Config::integer('credits.welcome_bonus'),
        ]);
        $user->creditTransactions()->create([
            'type' => CreditTransactionType::Purchase,
            'amount' => 10_000_000_000,
        ]);

        $prices = $pixelforge->prices();

        $samples = [
            ['Aerial view of rice terraces after rain', Size::Landscape, Quality::High],
            ['Studio photo of a ceramic vase, soft shadows', Size::Portrait, Quality::Medium],
            ['Album cover, a single plum on linen', Size::Square, Quality::Medium],
            ['Isometric harbour town at night', Size::Landscape, Quality::Medium],
            ['Forest floor macro, morning dew', Size::Portrait, Quality::High],
            ['Poster, tennis court at golden hour', Size::Portrait, Quality::Medium],
            ['Spiral staircase from below, monochrome', Size::Square, Quality::Low],
            ['Minimal chair in a sunlit room', Size::Square, Quality::Medium],
            ['Wind patterns on dunes, top-down', Size::Landscape, Quality::Low],
        ];

        foreach ($samples as $position => [$prompt, $size, $quality]) {
            $estimate = $prices->estimate($prompt, $size, $quality, 0);
            $usage = new TokenUsage(
                $estimate->expected->inputText,
                0,
                $estimate->expected->output,
                random_int(intdiv($estimate->expected->reasoning, 2), $estimate->maximum->reasoning),
            );
            $cost = $usage->cost($estimate->rates);
            $sentAt = now()->subMinutes(($position + 1) * 13);
            $completedAt = $sentAt->addSeconds(random_int(10, 30));

            $generation = Generation::factory()->for($user)->succeeded()->create([
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'min_price' => $markup->apply($estimate->minimumCost()),
                'estimated_price' => $markup->apply($estimate->expectedCost()),
                'max_price' => $markup->apply($estimate->maximumCost()),
                'rates' => $estimate->rates,
                ...$usage->toArray(),
                'cost' => $cost,
                'price' => $markup->apply($cost),
                'created_at' => $sentAt,
                'started_at' => $sentAt->addSeconds(2),
                'completed_at' => $completedAt,
                'image_url_expires_at' => $completedAt->addDay(),
            ]);

            $user->creditTransactions()->create([
                'generation_id' => $generation->id,
                'type' => CreditTransactionType::Charge,
                'amount' => -$generation->price,
            ]);
        }

        $estimate = $prices->estimate('A test of the content filter', Size::Square, Quality::Medium, 0);

        Generation::factory()->for($user)->create([
            'status' => GenerationStatus::Failed,
            'prompt' => 'A test of the content filter',
            'min_price' => $markup->apply($estimate->minimumCost()),
            'estimated_price' => $markup->apply($estimate->expectedCost()),
            'max_price' => $markup->apply($estimate->maximumCost()),
            'error_code' => 'content_policy_violation',
            'error_message' => 'The prompt was flagged by the content filter.',
            'created_at' => now()->subMinutes(5),
        ]);
    }
}
