<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ゲスト雇用 + バトル自動開始リクエスト。
 * 認証は不要(未ログインでも、家門未作成の認証ユーザーでも可)。
 */
class GuestHireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
