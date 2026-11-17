<?php

namespace App\Concerns;

use App\Enums\Quality;
use App\Enums\Size;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

trait GenerationValidationRules
{
    /** @return array<string, array<int, Enum|string>> */
    protected function generationRules(bool $requirePrompt = true): array
    {
        return [
            'prompt' => [$requirePrompt ? 'required' : 'nullable', 'string', 'max:4000'],
            'size' => ['required', Rule::enum(Size::class)],
            'quality' => ['required', Rule::enum(Quality::class)],
            'reference_images' => ['nullable', 'array', 'max:4'],
            'reference_images.*' => ['required', 'url:http,https', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    protected function generationMessages(): array
    {
        return [
            'prompt.required' => 'Describe the image you want.',
            'prompt.max' => 'Keep the prompt under 4,000 characters.',
            'reference_images.max' => 'Add up to 4 reference images.',
            'reference_images.*.url' => 'Use a full http or https link to an image.',
        ];
    }

    public function prompt(): string
    {
        return $this->string('prompt')->trim()->toString();
    }

    public function size(): Size
    {
        return Size::from($this->string('size')->toString());
    }

    public function quality(): Quality
    {
        return Quality::from($this->string('quality')->toString());
    }

    /** @return list<string> */
    public function referenceImages(): array
    {
        return array_values($this->collect('reference_images')->map(fn (mixed $url) => (string) $url)->all());
    }
}
