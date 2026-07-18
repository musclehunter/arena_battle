<?php

namespace App\Http\Requests\Party;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:32'],
            'strategy' => ['required', 'in:aggressive,balanced,defensive'],
            'risk' => ['required', 'in:safe,normal,high'],
            'character_ids' => ['present', 'array', 'max:5'],
            'character_ids.*' => ['integer', 'distinct', 'exists:characters,id'],
        ];
    }
}
