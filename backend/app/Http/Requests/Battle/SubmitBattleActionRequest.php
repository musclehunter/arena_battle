<?php

namespace App\Http\Requests\Battle;

use App\Enums\BattleActionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitBattleActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(BattleActionType::class)],
            'token' => ['required', 'string', 'max:64'],
        ];
    }

    public function playerAction(): BattleActionType
    {
        return BattleActionType::from($this->validated('action'));
    }

    public function token(): string
    {
        return $this->validated('token');
    }
}
