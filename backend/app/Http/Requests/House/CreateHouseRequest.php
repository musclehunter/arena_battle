<?php

namespace App\Http\Requests\House;

use Illuminate\Foundation\Http\FormRequest;

class CreateHouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 認証 + まだ家門未作成 のみ許可
        $user = $this->user();

        return $user !== null && ! $user->house()->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:24'],
        ];
    }

    public function name(): string
    {
        return (string) $this->validated('name');
    }
}
