import { ref, reactive } from 'vue';

const STORAGE_KEY = 'master_cache_v1';
const VERSIONS_KEY = 'master_versions_v1';

// グローバル状態（シングルトン）
const master = reactive({
    skills: [],          // [{key, name, job, ...}]
    skillsByKey: {},     // {key: skill} のlookup用
    jobs: {},            // {job_key: {name, line, icon_key}}
    atb: {},             // {skills, max_gauge, ...}
    loaded: false,
    loading: false,
});

const versions = ref({});

function loadFromStorage() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const ver = localStorage.getItem(VERSIONS_KEY);
        if (raw) {
            const data = JSON.parse(raw);
            master.skills = data.skills || [];
            master.jobs = data.jobs || {};
            master.atb = data.atb || {};
            rebuildSkillsByKey();
        }
        if (ver) {
            versions.value = JSON.parse(ver);
        }
    } catch (e) {
        console.warn('[master] localStorage parse error', e);
    }
}

function saveToStorage() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
        skills: master.skills,
        jobs: master.jobs,
        atb: master.atb,
    }));
    localStorage.setItem(VERSIONS_KEY, JSON.stringify(versions.value));
}

function rebuildSkillsByKey() {
    master.skillsByKey = {};
    for (const s of master.skills) {
        master.skillsByKey[s.key] = s;
    }
}

/**
 * サーバからマスタデータを取得（差分更新）。
 * @param {boolean} force - trueなら強制全取得
 */
async function fetchMaster(force = false) {
    if (master.loading) return;
    master.loading = true;

    try {
        const since = force ? 0 : Math.max(
            versions.value.skills || 0,
            versions.value.atb || 0,
        );
        const url = route('master.index') + (force ? '' : `?since=${since}`);
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(`master fetch failed: ${res.status}`);
        const json = await res.json();

        // 全キャッシュ無効化フラグ
        if (json.invalidate_all) {
            clearCache();
        }

        // 差分データをマージ
        if (json.data?.skills) {
            master.skills = json.data.skills;
            rebuildSkillsByKey();
        }
        if (json.data?.jobs) {
            master.jobs = json.data.jobs;
        }
        if (json.data?.atb) {
            master.atb = json.data.atb;
        }

        // バージョン更新
        if (json.versions) {
            versions.value = { ...versions.value, ...json.versions };
        }

        saveToStorage();
        master.loaded = true;
    } catch (e) {
        console.error('[master] fetch error', e);
    } finally {
        master.loading = false;
    }
}

/**
 * ローカルキャッシュを全消去。
 */
function clearCache() {
    localStorage.removeItem(STORAGE_KEY);
    localStorage.removeItem(VERSIONS_KEY);
    master.skills = [];
    master.skillsByKey = {};
    master.jobs = {};
    master.atb = {};
    versions.value = {};
}

/**
 * 初期化（アプリ起動時に呼ぶ）。
 */
async function initMaster() {
    loadFromStorage();
    await fetchMaster(false);
}

export function useMasterData() {
    return {
        master,
        versions,
        fetchMaster,
        clearCache,
        initMaster,
        // ヘルパー
        skillName: (key) => master.skillsByKey[key]?.name ?? master.atb.skills?.[key]?.name ?? key,
        skillByKey: (key) => master.skillsByKey[key] ?? null,
        jobName: (jobKey) => master.jobs[jobKey]?.name ?? jobKey,
    };
}
