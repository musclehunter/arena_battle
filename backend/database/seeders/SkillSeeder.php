<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        // 古いスキルを削除してから再登録
        Skill::query()->delete();

        $skills = config('skills.skills', []);

        foreach ($skills as $data) {
            Skill::create($data);
        }
    }
}
