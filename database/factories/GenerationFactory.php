<?php

namespace Database\Factories;

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

use function Illuminate\Support\enum_value;

/** @extends Factory<Generation> */
class GenerationFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Generation $generation) {
            if (! $generation->status->isFinished()) {
                $generation->user->creditTransactions()->create([
                    'generation_id' => $generation->id,
                    'type' => CreditTransactionType::Hold,
                    'amount' => -$generation->max_price,
                ]);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => GenerationStatus::Pending,
            'prompt' => fake()->sentence(),
            'size' => Size::Landscape,
            'quality' => Quality::High,
            'reference_images' => ['https://example.com/reference.png'],
            'min_price' => 347_756_250,
            'estimated_price' => 402_450_000,
            'max_price' => 457_150_000,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn () => [
            'provider_id' => 'gen_'.Str::ulid(),
            'status' => GenerationStatus::Queued,
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
        ]);
    }

    public function processing(): static
    {
        return $this->queued()->state(fn () => [
            'status' => GenerationStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function succeeded(): static
    {
        return $this->processing()->state(fn () => [
            'status' => GenerationStatus::Succeeded,
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
            'cost' => 305_510_000,
            'price' => 381_887_500,
            'image_url' => fn (array $attributes) => sprintf(
                'https://picsum.photos/seed/%s/%s',
                Str::random(10),
                str_replace('x', '/', enum_value($attributes['size'])),
            ),
            'image_url_expires_at' => now()->addDay(),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->processing()->state(fn () => [
            'status' => GenerationStatus::Failed,
            'error_code' => 'generation_failed',
            'error_message' => "The model couldn't produce an image for the prompt.",
            'completed_at' => now(),
        ]);
    }
}
