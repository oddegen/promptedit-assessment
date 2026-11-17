<?php

namespace App\Http\Requests\Generations;

use App\Concerns\GenerationValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DraftGenerationRequest extends FormRequest
{
    use GenerationValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->generationRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->generationMessages();
    }

    /** @return array{prompt: string, size: string, quality: string} */
    public function draft(): array
    {
        return [
            'prompt' => $this->prompt(),
            'size' => $this->size()->value,
            'quality' => $this->quality()->value,
        ];
    }
}
