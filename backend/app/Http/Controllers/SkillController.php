<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Services\Character\SkillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SkillController extends Controller
{
    public function index(Request $request, SkillService $service): Response
    {
        $house = $request->user()->house;
        $house->load('characters.skills', 'characters.preset');

        $characters = $house->characters->map(fn (Character $character) => [
            'id' => $character->id,
            'name' => $character->name,
            'level' => $character->level,
            'skill_points' => $character->skill_points,
            'job' => $character->preset?->icon_key,
            'skills' => $service->learnableSkills($character),
        ]);

        return Inertia::render('Skills/Index', ['characters' => $characters]);
    }

    public function learn(Request $request, Character $character, SkillService $service): JsonResponse
    {
        if ($character->house_id !== $request->user()->house->id) {
            abort(403);
        }

        $data = $request->validate(['skill_id' => ['required', 'integer']]);

        try {
            $result = $service->learn($character, $data['skill_id']);
            return response()->json($result);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
