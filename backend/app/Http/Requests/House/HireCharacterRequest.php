<?php

namespace App\Http\Requests\House;

use Illuminate\Foundation\Http\FormRequest;

class HireCharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->house !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'character_id' => ['required', 'integer', 'exists:characters,id'],
        ];
    }

    public function characterId(): int
    {
        return (int) $this->validated('character_id');
    }
}
