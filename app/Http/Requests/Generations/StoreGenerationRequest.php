<?php

namespace App\Http\Requests\Generations;

use App\Concerns\GenerationValidationRules;
use App\Models\Generation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    use GenerationValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->generationRules(),
            'parent_id' => ['nullable', 'string', Rule::exists('generations', 'id')->where('user_id', $this->user()?->getAuthIdentifier())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->generationMessages();
    }

    public function versionOf(): ?Generation
    {
        return $this->filled('parent_id') ? Generation::query()->find($this->string('parent_id')->toString()) : null;
    }
}
