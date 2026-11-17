<?php

namespace App\Http\Requests\Credits;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class PurchaseCreditsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pack' => ['required', 'integer', Rule::in(Config::array('credits.packs'))],
        ];
    }

    public function dollars(): int
    {
        return $this->integer('pack');
    }
}
