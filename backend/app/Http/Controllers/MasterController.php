<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MasterController extends Controller
{
    /**
     * マスタデータをバージョン付きで返す。
     * クライアントは ?since=バージョン を渡して差分更新可能。
     * invalidate=1 のときはキャッシュ無効化フラグを立てる（管理者用）。
     */
    public function index(Request $request): JsonResponse
    {
        $since = (int) $request->query('since', 0);

        // 全キャッシュ無効化フラグ（DBまたはファイルベース）
        $invalidateFlag = Cache::get('master:invalidate', false);

        // 各マスタの現在バージョン
        $skillVersion = (int) Cache::get('master:version:skills', 1);
        $atbVersion = (int) Cache::get('master:version:atb', 1);

        $response = [
            'invalidate_all' => $invalidateFlag,
            'versions' => [
                'skills' => $skillVersion,
                'atb' => $atbVersion,
            ],
            'data' => [],
        ];

        // 差分更新: クライアントのバージョン < サーバのバージョン ならデータを返す
        if ($invalidateFlag || $since < $skillVersion) {
            $response['data']['skills'] = Skill::orderBy('job')->orderBy('unlock_level')->get()->map(fn ($s) => [
                'id' => $s->id,
                'key' => $s->key,
                'name' => $s->name,
                'job' => $s->job,
                'line' => $s->line,
                'description' => $s->description,
                'sp_cost' => $s->sp_cost,
                'unlock_level' => $s->unlock_level,
                'scales_with' => $s->scales_with,
                'power' => $s->power,
                'cast_gauge' => $s->cast_gauge,
                'cooldown_gauge' => $s->cooldown_gauge,
                'element' => $s->element,
                'target_type' => $s->target_type,
                'target_count' => $s->target_count,
                'effect_type' => $s->effect_type,
                'effect_power' => $s->effect_power,
                'effect_duration' => $s->effect_duration,
                'is_passive' => $s->is_passive,
            ])->toArray();
        }

        if ($invalidateFlag || $since < $atbVersion) {
            $response['data']['atb'] = [
                'skills' => config('atb.skills'),
                'max_gauge' => config('atb.max_gauge'),
                'dex_tiers' => config('atb.dex_tiers'),
                'guard_def_multiplier' => config('atb.guard_def_multiplier'),
            ];
        }

        // ジョブ定義（スキル画面用）
        $response['data']['jobs'] = config('skills.jobs');

        return response()->json($response);
    }

    /**
     * 管理者: マスタのバージョンを上げる（差分更新のトリガー）。
     */
    public function bump(Request $request): JsonResponse
    {
        $type = $request->input('type', 'skills');
        $key = "master:version:{$type}";
        Cache::forever($key, (int) Cache::get($key, 1) + 1);
        return response()->json(['ok' => true, 'type' => $type, 'version' => Cache::get($key)]);
    }

    /**
     * 管理者: 全キャッシュ無効化フラグを立てる。
     */
    public function invalidate(Request $request): JsonResponse
    {
        Cache::forever('master:invalidate', true);
        return response()->json(['ok' => true, 'invalidate' => true]);
    }

    /**
     * 管理者: 全キャッシュ無効化フラグを解除。
     */
    public function clearInvalidate(Request $request): JsonResponse
    {
        Cache::forget('master:invalidate');
        return response()->json(['ok' => true, 'invalidate' => false]);
    }
}
