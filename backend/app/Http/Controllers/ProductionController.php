<?php

namespace App\Http\Controllers;

use App\Models\ProductionJob;
use App\Services\ProductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProductionController extends Controller
{
    public function index(Request $request, ProductionService $service): Response
    {
        $house = $request->user()->house;
        $service->completeDueJobs($house);

        return Inertia::render('Production/Index', $this->data($house, $service));
    }

    public function state(Request $request, ProductionService $service): JsonResponse
    {
        $house = $request->user()->house;
        $service->completeDueJobs($house);
        return response()->json($this->data($house->fresh(), $service));
    }

    public function start(Request $request, ProductionService $service): JsonResponse
    {
        $data = $request->validate(['character_id' => ['required', 'integer'], 'activity_key' => ['required', 'string']]);
        $house = $request->user()->house;

        try {
            $service->start($house, $data['character_id'], $data['activity_key']);
            return response()->json($this->data($house->fresh(), $service));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function collect(Request $request, ProductionJob $productionJob, ProductionService $service): JsonResponse
    {
        $house = $request->user()->house;

        try {
            $service->collect($house, $productionJob->id);
            return response()->json($this->data($house->fresh(), $service));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function data($house, ProductionService $service): array
    {
        $jobs = ProductionJob::query()->where('house_id', $house->id)->with('character.preset')->latest()->get()->map(fn (ProductionJob $job) => [
            'id' => $job->id,
            'activity_key' => $job->activity_key,
            'activity_name' => config("production.activities.{$job->activity_key}.name"),
            'status' => $job->status,
            'output' => $job->output,
            'character' => ['id' => $job->character->id, 'name' => $job->character->name],
            'completes_at' => $job->completes_at?->toIso8601String(),
        ])->values();

        return [
            'house' => ['gold' => $house->gold],
            'characters' => $house->characters()->with('preset')->get()->map(fn ($character) => [
                'id' => $character->id,
                'name' => $character->name,
                'level' => $character->level,
                'icon_key' => $character->preset->icon_key,
                'icon_index' => $character->icon_index,
                'gender' => strtolower($character->gender?->name ?? 'unknown'),
                'busy' => $jobs->contains(fn ($job) => $job['character']['id'] === $character->id && $job['status'] === 'in_progress'),
            ])->values(),
            'activities' => collect(config('production.activities'))->map(fn ($activity, $key) => ['key' => $key, ...$activity, 'duration_seconds' => (int) config('production.duration_seconds')])->values(),
            'inventory' => $service->inventory($house),
            'jobs' => $jobs,
        ];
    }
}
