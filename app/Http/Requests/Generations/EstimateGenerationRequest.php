<?php

namespace App\Http\Requests\Generations;

use App\Concerns\GenerationValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EstimateGenerationRequest extends FormRequest
{
    use GenerationValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->generationRules(requirePrompt: false);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->generationMessages();
    }
}
