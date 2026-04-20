<?php

namespace App\Http\Requests\Battle;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 家門プレイヤーが自家門のキャラでバトル開始するリクエスト。
 * (ゲスト雇用経由のバトル開始は GuestHireRequest 側)
 */
class StartBattleRequest extends FormRequest
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
