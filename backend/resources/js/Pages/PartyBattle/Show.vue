<script setup>
import CharacterIcon from '@/Components/CharacterIcon.vue';
import BattleLogPanel from '@/Components/Battle/BattleLogPanel.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useMasterData } from '@/Composables/useMasterData';

const { master, skillName } = useMasterData();

const props = defineProps({ battle: Object });
const battle = ref(props.battle);
const autoEnabled = ref(Boolean(props.battle.auto) || localStorage.getItem('arena-atb-auto') === '1');
const soundEnabled = ref(false);
const damagePopups = ref([]);
const activeAttackers = ref([]);
const hitTargets = ref([]);
const fieldShaking = ref(false);
const paused = ref(false);
const speed = ref(1);
const cameraMode = ref('topdown');
const followTarget = ref(null);
const selectedStrategy = ref(autoEnabled.value ? 'aggressive' : 'balanced');
const retreatCountdown = ref(0);
const retreatMessage = ref('');
const elapsedSeconds = ref(0);
let audioContext;
let lastUpdatedAt = props.battle.updated_at ?? 0;
let retreatTimer;
let elapsedTimer;
const pendingReservations = new Set();
const battleLogs = ref([]);
const loggedEventIds = new Set();

const riskLabels = { safe: '安全', normal: '通常', high: '高リスク' };

const hpPercent = (member) => `${Math.max(0, member.hp / member.max_hp * 100)}%`;
const isDefeated = (member) => member.hp <= 0;
const memberKey = (side, index) => `${side}-${index}`;
const playerRows = computed(() => [battle.value.players.slice(0, 3), battle.value.players.slice(3)]);
const enemyRows = computed(() => [battle.value.enemies.slice(0, 3), battle.value.enemies.slice(3)]);
const castGauge = (skill) => {
    const atbSkills = master.atb?.skills || {};
    if (atbSkills[skill]) return atbSkills[skill].cast_gauge;
    const s = master.skillsByKey[skill];
    return s?.cast_gauge ?? 5000;
};
const atbMaxGauge = () => master.atb?.max_gauge ?? 10000;
const atbPercent = (member) => {
    if (! member) return 0;
    if (member.phase === 'cooldown') return Math.min(100, (member.cooldown / 4000) * 100);
    const need = castGauge(member.reserved_skill) || atbMaxGauge();
    return Math.min(100, (member.gauge / need) * 100);
};
const phaseLabel = (member) => {
    if (member.hp <= 0) return '戦闘不能';
    if (member.phase === 'input') return '行動選択';
    if (member.phase === 'casting') return '詠唱中';
    if (member.phase === 'cooldown') return '待機';
    return member.phase;
};
const canAct = (member) => member.hp > 0 && member.phase === 'input' && ! autoEnabled.value;

const playTone = (frequency, duration, type = 'sine', volume = 0.035) => {
    if (! soundEnabled.value) return;
    audioContext ??= new AudioContext();
    const oscillator = audioContext.createOscillator();
    const gain = audioContext.createGain();
    oscillator.type = type;
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(volume, audioContext.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + duration);
    oscillator.connect(gain).connect(audioContext.destination);
    oscillator.start();
    oscillator.stop(audioContext.currentTime + duration);
};

const enableSound = async () => {
    soundEnabled.value = ! soundEnabled.value;
    if (soundEnabled.value) {
        audioContext ??= new AudioContext();
        await audioContext.resume();
        playTone(660, 0.09, 'triangle', 0.05);
    }
};

const wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds / speed.value));

const playEvents = async (events) => {
    const attackerKeys = events.map((e) => memberKey(e.side, e.index));
    const targetKeys = events.map((e) => memberKey(e.target_side, e.target_index));
    activeAttackers.value = attackerKeys;
    await wait(220);
    damagePopups.value = events.map((e) => {
        const targetKey = memberKey(e.target_side, e.target_index);
        return { id: `evt-${e.id}-${targetKey}`, key: targetKey, damage: e.damage };
    });
    hitTargets.value = targetKeys;
    fieldShaking.value = true;
    events.forEach(() => playTone(130, 0.09, 'sawtooth', 0.045));
    await wait(520);
    activeAttackers.value = [];
    hitTargets.value = [];
    damagePopups.value = [];
    fieldShaking.value = false;
    await wait(140);
};

const applyState = async (state) => {
    if (paused.value || ! state || state.updated_at <= lastUpdatedAt) return;
    const newEvents = (state.events ?? []).filter((event) => ! loggedEventIds.has(event.id));
    lastUpdatedAt = state.updated_at;
    battle.value = pendingReservations.size
        ? {
            ...state,
            players: state.players.map((player) => pendingReservations.has(player.character_id)
                ? battle.value.players.find((current) => current.character_id === player.character_id) ?? player
                : player),
        }
        : state;
    appendLogEvents(newEvents);
    if (newEvents.length) await playEvents(newEvents);
    if (state.status === 'finished') playTone(state.winner === 'player' ? 780 : 160, 0.45, 'triangle', 0.06);
};

const reserve = async (member, skill) => {
    if (! canAct(member)) return;
    const previousBattle = battle.value;
    pendingReservations.add(member.character_id);
    battle.value = {
        ...previousBattle,
        players: previousBattle.players.map((player) => player.character_id === member.character_id
            ? { ...player, reserved_skill: skill, phase: 'casting', gauge: 0, guard: false }
            : player),
    };
    try {
        const response = await fetch(route('party-battles.actions', { partyBattle: battle.value.id }), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: JSON.stringify({ character_id: member.character_id, skill }),
        });
        if (response.ok) {
            battle.value = await response.json();
        } else {
            battle.value = previousBattle;
            if (response.status === 422) {
                const stateRes = await fetch(route('party-battles.state', { partyBattle: battle.value.id }), { headers: { Accept: 'application/json' } });
                if (stateRes.ok) battle.value = await stateRes.json();
            }
        }
    } catch (e) {
        battle.value = previousBattle;
    } finally {
        pendingReservations.delete(member.character_id);
    }
};

const sendAuto = async (next) => {
    localStorage.setItem('arena-atb-auto', next ? '1' : '0');
    const response = await fetch(route('party-battles.auto', { partyBattle: battle.value.id }), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: JSON.stringify({ enabled: next }),
    });
    if (response.ok) {
        autoEnabled.value = next;
        battle.value = await response.json();
    } else {
        autoEnabled.value = next;
    }
    selectedStrategy.value = next ? 'aggressive' : 'balanced';
};

const isAutoStrategy = (s) => ['aggressive', 'balanced', 'defensive', 'skill', 'heal'].includes(s);

const onStrategyChange = async () => {
    const next = isAutoStrategy(selectedStrategy.value);
    if (autoEnabled.value !== next) await sendAuto(next);
};

const setSpeed = (value) => { speed.value = value; };
const togglePause = () => { paused.value = ! paused.value; };

const setCameraMode = (mode, side = null, index = null) => {
    cameraMode.value = mode;
    followTarget.value = mode === 'follow' && side !== null && index !== null ? { side, index } : null;
};

const startRetreat = () => {
    if (retreatCountdown.value > 0) return;
    if (!confirm('撤退を試みますか？ 成功すれば戦闘を終了します。')) return;
    retreatMessage.value = '撤退準備中…';
    retreatCountdown.value = 3;
    retreatTimer = window.setInterval(() => {
        retreatCountdown.value--;
        if (retreatCountdown.value <= 0) {
            window.clearInterval(retreatTimer);
            retreatMessage.value = '撤退しました。';
            setTimeout(() => router.visit(route('houses.mine')), 500);
        }
    }, 1000);
};

const actorName = (side, index) => battle.value[side]?.[index]?.name ?? '?';
const formatLog = (e) => {
    const actor = actorName(e.side, e.index);
    const skill = skillName(e.skill) ?? e.skill;
    if (e.target_side === null || e.target_index === null) {
        return `${actor}が${skill}を使用`;
    }
    const target = actorName(e.target_side, e.target_index);
    const amount = e.damage < 0 ? `回復 ${-e.damage}` : `${e.damage} ダメージ`;
    return `${actor}が${target}に『${skill}』 ${amount}`;
};

const elapsedText = computed(() => {
    const m = Math.floor(elapsedSeconds.value / 60).toString().padStart(2, '0');
    const s = (elapsedSeconds.value % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
});

const appendLogEvents = (events) => {
    const now = new Date();
    const time = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}`;
    const logs = events.filter((event) => ! loggedEventIds.has(event.id)).map((event, index) => {
        loggedEventIds.add(event.id);
        return { id: event.id, turn_number: battleLogs.value.length + index + 1, summary_text: `${time} ${formatLog(event)}` };
    });
    if (logs.length) battleLogs.value = [...battleLogs.value, ...logs];
};

const formattedLogs = computed(() => battleLogs.value);

onMounted(() => {
    appendLogEvents(battle.value.events ?? []);
    const savedAuto = localStorage.getItem('arena-atb-auto') === '1';
    selectedStrategy.value = savedAuto ? 'aggressive' : 'balanced';
    if (savedAuto && ! battle.value.auto) {
        fetch(route('party-battles.auto', { partyBattle: battle.value.id }), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: JSON.stringify({ enabled: true }),
        }).then((r) => r.ok ? r.json() : null).then((s) => { if (s) battle.value = s; });
    }
    window.Echo.private(`party-battle.${battle.value.id}`).listen('.state.updated', (payload) => applyState(payload.state));

    elapsedTimer = window.setInterval(() => { elapsedSeconds.value++; }, 1000);
});
onBeforeUnmount(() => {
    window.Echo?.leave(`party-battle.${battle.value.id}`);
    if (elapsedTimer) window.clearInterval(elapsedTimer);
    if (retreatTimer) window.clearInterval(retreatTimer);
});
</script>

<template>
    <Head :title="`遠征 #${battle.id}`" />
    <main class="min-h-screen bg-gray-950 p-3 text-gray-100 md:p-6">
        <div class="mx-auto max-w-7xl space-y-4">
            <!-- 上部情報バー -->
            <header class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-700 bg-gray-900/70 px-4 py-3">
                <div>
                    <h1 class="font-bold">魔境遠征</h1>
                    <p class="text-xs text-gray-400">
                        戦況: {{ battle.status === 'in_progress' ? '交戦中' : '終了' }} /
                        エリア {{ battle.area ?? '1' }}/5 /
                        危険度: {{ riskLabels[battle.risk] ?? battle.risk }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button @click="togglePause" class="rounded-lg border px-2 py-1 text-xs transition"
                            :class="paused ? 'border-rose-300 text-rose-200' : 'border-gray-600 text-gray-300 hover:border-gray-400'">
                        {{ paused ? '再開' : '一時停止' }}
                    </button>
                    <button @click="sendAuto(!autoEnabled)" class="rounded-lg border px-2 py-1 text-xs transition"
                            :class="autoEnabled ? 'border-emerald-300 text-emerald-200' : 'border-gray-600 text-gray-300 hover:border-emerald-300 hover:text-emerald-200'">
                        オート {{ autoEnabled ? 'ON' : 'OFF' }}
                    </button>
                    <div class="flex items-center gap-1 rounded-lg border border-gray-600 p-1">
                        <button v-for="v in [1,2,4]" :key="v" @click="setSpeed(v)"
                                :class="speed === v ? 'bg-amber-600/40 text-amber-200' : 'text-gray-400 hover:text-white'"
                                class="px-2 py-0.5 rounded text-xs">x{{ v }}</button>
                    </div>
                    <div class="text-xs text-gray-400">経過 {{ elapsedText }}</div>
                    <select v-model="cameraMode" class="rounded-lg border-gray-600 bg-gray-900 text-xs px-2 py-1">
                        <option value="topdown">俯瞰</option>
                        <option value="follow">味方追従</option>
                        <option value="enemy">敵追従</option>
                    </select>
                    <button @click="enableSound" class="rounded-lg border px-2 py-1 text-xs transition"
                            :class="soundEnabled ? 'border-amber-300 text-amber-200' : 'border-gray-600 text-gray-300 hover:border-amber-300 hover:text-amber-200'">
                        音 {{ soundEnabled ? 'ON' : 'OFF' }}
                    </button>
                    <Link :href="route('houses.mine')" class="text-sm text-gray-400 hover:text-white">家門へ戻る</Link>
                </div>
            </header>

            <section class="grid gap-4 lg:grid-cols-[1fr_1.5fr_1fr]">
                <!-- 味方カラム -->
                <div class="space-y-2">
                    <h2 class="text-sm font-bold text-emerald-300">遠征隊</h2>
                    <article v-for="(member, index) in battle.players" :key="member.character_id"
                             @click="setCameraMode('follow', 'players', index)"
                             class="cursor-pointer flex flex-col gap-2 rounded-xl border border-gray-700 bg-gray-900/70 p-2 transition"
                             :class="{ 'opacity-40 grayscale': isDefeated(member), 'panel-hit': hitTargets.includes(memberKey('players', index)), 'ring-1 ring-emerald-500/50': followTarget && followTarget.side === 'players' && followTarget.index === index }">
                        <div class="flex items-center gap-3">
                            <CharacterIcon :icon-key="member.icon_key" :icon-index="member.icon_index" :gender="member.gender" :name="member.name" :size="48" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-bold">{{ member.name }} <span class="text-xs text-gray-500">Lv.{{ member.level }}</span></div>
                                <div class="mt-1 h-2 overflow-hidden rounded bg-gray-700"><div class="h-full bg-emerald-500 transition-all duration-500" :style="{ width: hpPercent(member) }" /></div>
                                <small>{{ member.hp }} / {{ member.max_hp }} · {{ phaseLabel(member) }}</small>
                                <div class="mt-0.5 flex gap-2 text-[10px] text-gray-500">
                                    <span title="攻撃力">ATK {{ member.atk }}</span>
                                    <span title="防御力">DEF {{ member.def }}</span>
                                    <span title="敏捷">DEX {{ member.speed }}</span>
                                    <span v-if="member.int" title="知力">INT {{ member.int }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="canAct(member)" class="flex flex-wrap gap-1">
                            <button @click.stop="reserve(member, 'normal')" class="rounded bg-gray-800 px-2 py-1 text-xs hover:bg-gray-700">通常</button>
                            <button @click.stop="reserve(member, 'guard')" class="rounded bg-gray-800 px-2 py-1 text-xs hover:bg-gray-700">防御</button>
                            <button v-for="sk in (member.learned_skills || [])" :key="sk" @click.stop="reserve(member, sk)"
                                class="rounded bg-indigo-800 px-2 py-1 text-xs hover:bg-indigo-700">{{ skillName(sk) }}</button>
                        </div>
                    </article>
                </div>

                <!-- 俯瞰フィールド -->
                <div class="battlefield" :class="{ 'field-shake': fieldShaking, 'camera-follow': cameraMode === 'follow', 'camera-enemy': cameraMode === 'enemy' }">
                    <div class="battlefield-bg" /><div class="battlefield-vignette" /><div class="battlefield-fog fog-one" /><div class="battlefield-fog fog-two" />
                    <span v-for="spark in 16" :key="spark" class="ember" :style="{ left: `${(spark * 29) % 100}%`, animationDelay: `${spark * -0.45}s`, animationDuration: `${4 + spark % 4}s` }" />
                    <div class="field-label allies-label">遠征隊</div><div class="field-label enemies-label">魔境の群れ</div>
                    <div class="unit-zone player-zone">
                        <div v-for="(row, rowIndex) in playerRows" :key="`player-row-${rowIndex}`" class="unit-row">
                            <div v-for="(member, columnIndex) in row" :key="member.character_id"
                                 @click="setCameraMode('follow', 'players', rowIndex * 3 + columnIndex)"
                                 class="field-unit player-unit cursor-pointer"
                                 :class="{ defeated: isDefeated(member), attacking: activeAttackers.includes(memberKey('players', rowIndex * 3 + columnIndex)), hit: hitTargets.includes(memberKey('players', rowIndex * 3 + columnIndex)), focused: followTarget && followTarget.side === 'players' && followTarget.index === rowIndex * 3 + columnIndex }">
                                <CharacterIcon :icon-key="member.icon_key" :icon-index="member.icon_index" :gender="member.gender" :name="member.name" :size="54" />
                                <span class="unit-name">{{ member.name }}</span>
                                <span class="atb-ring" :style="{ '--atb-angle': `${atbPercent(member)}%` }" />
                            </div>
                        </div>
                    </div>
                    <div class="versus"><span>VS</span><small v-if="battle.status === 'in_progress'">{{ autoEnabled ? 'AUTO' : 'MANUAL' }}</small></div>
                    <div class="unit-zone enemy-zone">
                        <div v-for="(row, rowIndex) in enemyRows" :key="`enemy-row-${rowIndex}`" class="unit-row">
                            <div v-for="(member, columnIndex) in row" :key="`${member.preset_id}-${rowIndex}-${columnIndex}`"
                                 @click="setCameraMode('enemy', 'enemies', rowIndex * 3 + columnIndex)"
                                 class="field-unit enemy-unit cursor-pointer"
                                 :class="{ defeated: isDefeated(member), attacking: activeAttackers.includes(memberKey('enemies', rowIndex * 3 + columnIndex)), hit: hitTargets.includes(memberKey('enemies', rowIndex * 3 + columnIndex)), focused: followTarget && followTarget.side === 'enemies' && followTarget.index === rowIndex * 3 + columnIndex }">
                                <CharacterIcon :icon-key="member.icon_key" :icon-index="member.icon_index" :gender="member.gender" :name="member.name" :size="54" />
                                <span class="unit-name">{{ member.name }}</span>
                                <span class="atb-ring" :style="{ '--atb-angle': `${atbPercent(member)}%` }" />
                            </div>
                        </div>
                    </div>
                    <span v-for="popup in damagePopups" :key="popup.id" class="damage-popup" :class="popup.key.startsWith('players') ? 'popup-player' : 'popup-enemy'">
                        <template v-if="popup.damage < 0">+{{-popup.damage}}</template>
                        <template v-else>-{{ popup.damage }}</template>
                    </span>

                    <!-- 戦闘結果オーバーレイ -->
                    <div v-if="battle.status !== 'in_progress'" class="result-overlay">
                        <div class="result-card">
                            <p class="result-kicker">EXPEDITION RESULT</p>
                            <h2 :class="battle.winner === 'player' ? 'text-amber-300' : 'text-rose-300'">{{ battle.winner === 'player' ? '遠征成功' : '遠征失敗' }}</h2>
                            <p v-if="battle.winner === 'player'" class="text-amber-200">家門資金 +{{ battle.reward_gold }} G</p>
                            <div v-if="battle.level_ups && battle.level_ups.length" class="mt-3 space-y-1">
                                <p v-for="lu in battle.level_ups" :key="lu.character_id" class="text-sm text-emerald-300">
                                    {{ lu.name }} Lv.{{ lu.new_level - lu.levels_gained }} → Lv.{{ lu.new_level }}<span v-if="lu.preset_switched" class="text-amber-300"> ★成長変化</span><span v-if="lu.gold_gained" class="text-yellow-300"> +{{ lu.gold_gained }}G</span>
                                </p>
                            </div>
                            <div class="mt-4 flex gap-2 justify-center">
                                <Link :href="route('party-battles.result', { partyBattle: battle.id })" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold hover:bg-amber-500">戦闘結果へ</Link>
                                <Link :href="route('parties.edit')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold hover:bg-indigo-500">編成を見直す</Link>
                                <Link :href="route('houses.mine')" class="rounded-lg bg-gray-700 px-4 py-2 text-sm font-bold hover:bg-gray-600">家門へ戻る</Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 敵カラム -->
                <div class="space-y-2">
                    <h2 class="text-sm font-bold text-rose-300">魔境の群れ</h2>
                    <article v-for="(member, index) in battle.enemies" :key="`${member.preset_id}-${index}`"
                             @click="setCameraMode('enemy', 'enemies', index)"
                             class="cursor-pointer flex items-center gap-3 rounded-xl border border-gray-700 bg-gray-900/70 p-2 transition"
                             :class="{ 'opacity-40 grayscale': isDefeated(member), 'panel-hit': hitTargets.includes(memberKey('enemies', index)), 'ring-1 ring-rose-500/50': followTarget && followTarget.side === 'enemies' && followTarget.index === index }">
                        <CharacterIcon :icon-key="member.icon_key" :icon-index="member.icon_index" :gender="member.gender" :name="member.name" :size="48" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-bold">{{ member.name }} <span class="text-xs text-gray-500">Lv.{{ member.level }}</span></div>
                            <div class="mt-1 h-2 overflow-hidden rounded bg-gray-700"><div class="h-full bg-rose-500 transition-all duration-500" :style="{ width: hpPercent(member) }" /></div>
                            <small>{{ member.hp }} / {{ member.max_hp }} · {{ phaseLabel(member) }}</small>
                            <div class="mt-0.5 flex gap-2 text-[10px] text-gray-500">
                                <span title="攻撃力">ATK {{ member.atk }}</span>
                                <span title="防御力">DEF {{ member.def }}</span>
                                <span title="敏捷">DEX {{ member.speed }}</span>
                                <span v-if="member.int" title="知力">INT {{ member.int }}</span>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- 行動方針・撤退 -->
            <section class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-700 bg-gray-900/70 p-4 space-y-3">
                    <h3 class="text-sm font-bold text-gray-200">行動方針</h3>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="text-sm text-gray-400">全体方針</label>
                        <select v-model="selectedStrategy" @change="onStrategyChange" class="rounded-lg border-gray-700 bg-gray-900 text-sm px-3 py-1.5">
                            <option value="aggressive">攻撃的（オート）</option>
                            <option value="balanced">均衡（オート）</option>
                            <option value="defensive">防御的（オート）</option>
                            <option value="wait">待機的</option>
                            <option value="skill">スキル優先</option>
                            <option value="heal">回復優先</option>
                        </select>
                    </div>
                    <p class="text-xs text-gray-500">オート方針はサーバ側の自動行動に反映されます。待機的・スキル優先・回復優先は今後の拡張です。</p>
                </div>

                <div class="rounded-xl border border-gray-700 bg-gray-900/70 p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-gray-200">撤退</h3>
                        <p class="text-xs text-gray-500">現在の撤退成功率：{{ battle.retreat_rate ?? 85 }}%</p>
                        <p v-if="retreatMessage" class="text-xs text-amber-300 mt-1">{{ retreatMessage }}</p>
                    </div>
                    <button @click="startRetreat" :disabled="retreatCountdown > 0 || battle.status !== 'in_progress'"
                            class="rounded-lg bg-rose-700 hover:bg-rose-600 disabled:opacity-40 disabled:cursor-not-allowed px-5 py-2 text-sm font-bold transition">
                        {{ retreatCountdown > 0 ? `撤退中…${retreatCountdown}` : '撤退する' }}
                    </button>
                </div>
            </section>

            <!-- 戦闘ログ -->
            <BattleLogPanel :logs="formattedLogs" />
        </div>
    </main>
</template>

<style scoped>
@property --atb-angle { syntax: '<percentage>'; inherits: false; initial-value: 0%; }
.battlefield { position: relative; min-height: 430px; overflow: hidden; border: 1px solid #374151; border-radius: 1rem; background: #0b1220; isolation: isolate; transition: transform .5s ease; }
.battlefield.camera-follow { transform: translateX(8%) scale(1.05); }
.battlefield.camera-enemy { transform: translateX(-8%) scale(1.05); }
.battlefield-bg { position: absolute; inset: 0; background: url('/images/backgrounds/arena.png') center / cover; opacity: .36; transform: scale(1.06); animation: background-drift 18s ease-in-out infinite alternate; }
.battlefield-vignette { position: absolute; inset: 0; background: radial-gradient(ellipse at center, transparent 10%, rgba(2, 6, 23, .52) 72%, rgba(2, 6, 23, .93)); }
.battlefield::after { content: ''; position: absolute; inset: 14% 8%; border-radius: 50%; border: 1px solid rgba(251, 191, 36, .18); box-shadow: 0 0 75px rgba(245, 158, 11, .12), inset 0 0 60px rgba(16, 185, 129, .08); }
.battlefield-fog { position: absolute; width: 65%; height: 22%; border-radius: 50%; filter: blur(18px); background: rgba(148, 163, 184, .12); animation: fog-drift 10s linear infinite alternate; }
.fog-one { left: -14%; bottom: 20%; }.fog-two { right: -20%; top: 23%; animation-delay: -5s; }
.ember { position: absolute; z-index: 2; bottom: -10px; width: 3px; height: 3px; border-radius: 999px; background: #fbbf24; box-shadow: 0 0 8px #fb923c; opacity: 0; animation: ember-rise linear infinite; }
.field-label { position: absolute; z-index: 3; top: 5%; font-size: .65rem; font-weight: 700; letter-spacing: .16em; opacity: .7; }.allies-label { left: 7%; color: #6ee7b7; }.enemies-label { right: 7%; color: #fda4af; }
.unit-zone { position: absolute; z-index: 4; top: 21%; display: flex; flex-direction: column; gap: 3.8rem; width: 42%; }.player-zone { left: 5%; }.enemy-zone { right: 5%; align-items: flex-end; }.unit-row { display: flex; gap: .55rem; }.enemy-zone .unit-row { flex-direction: row-reverse; }.unit-row + .unit-row { margin-left: 1.7rem; }.enemy-zone .unit-row + .unit-row { margin-right: 1.7rem; margin-left: 0; }
.field-unit { position: relative; display: flex; flex-direction: column; align-items: center; gap: .15rem; transition: opacity .4s, filter .4s; }.unit-name { max-width: 58px; overflow: hidden; color: #e5e7eb; font-size: .62rem; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; text-shadow: 0 1px 3px #000; }.atb-ring { --atb-color: rgba(148, 163, 184, .45); --atb-angle: 35%; position: absolute; top: -4px; width: 62px; height: 62px; border-radius: 50%; background: conic-gradient(var(--atb-color) 0 var(--atb-angle), transparent var(--atb-angle)); -webkit-mask: radial-gradient(transparent 64%, #000 66%); mask: radial-gradient(transparent 64%, #000 66%); transition: --atb-angle .12s linear, --atb-color .2s ease; }.player-unit .atb-ring { --atb-color: #34d399; }.enemy-unit .atb-ring { --atb-color: #fb7185; }.field-unit.attacking .atb-ring { --atb-color: #fbbf24; --atb-angle: 100%; animation: none; box-shadow: 0 0 16px rgba(251, 191, 36, .8); }.field-unit.defeated { filter: grayscale(1); opacity: .28; transform: scale(.82); }.field-unit.hit { animation: unit-hit .45s ease-out; }.field-unit.focused { filter: drop-shadow(0 0 8px rgba(251, 191, 36, .55)); transform: scale(1.08); }.player-unit.attacking { animation: player-lunge .55s ease-in-out; }.enemy-unit.attacking { animation: enemy-lunge .55s ease-in-out; }
.versus { position: absolute; z-index: 5; top: 44%; left: 50%; display: flex; flex-direction: column; align-items: center; transform: translate(-50%, -50%); color: rgba(251, 191, 36, .75); text-shadow: 0 0 22px rgba(251, 191, 36, .45); }.versus span { font-size: 2.3rem; font-weight: 900; }.versus small { font-size: .58rem; letter-spacing: .25em; }
.damage-popup { position: absolute; z-index: 8; top: 37%; color: #fef2f2; font-size: 1.5rem; font-weight: 900; text-shadow: 0 2px 5px #7f1d1d; animation: damage-float .8s ease-out forwards; }.popup-player { left: 27%; }.popup-enemy { right: 27%; }
.result-overlay { position: absolute; z-index: 10; inset: 0; display: grid; place-items: center; background: rgba(2, 6, 23, .68); backdrop-filter: blur(4px); animation: overlay-in .5s ease-out; }.result-card { min-width: 250px; border: 1px solid rgba(251, 191, 36, .45); border-radius: 1rem; background: rgba(15, 23, 42, .94); padding: 1.5rem; text-align: center; box-shadow: 0 0 70px rgba(251, 191, 36, .18); }.result-card h2 { font-size: 1.9rem; font-weight: 900; }.result-kicker { margin-bottom: .3rem; color: #94a3b8; font-size: .6rem; letter-spacing: .2em; }
.panel-hit { border-color: #fb7185; background: rgba(127, 29, 29, .35); animation: panel-flash .45s ease-out; }
.field-shake { animation: field-shake .35s ease-in-out; }
@keyframes background-drift { to { transform: scale(1.16) translate(-1%, 1%); } } @keyframes fog-drift { to { transform: translateX(28%) scale(1.2); } } @keyframes ember-rise { 15% { opacity: .8; } 100% { transform: translateY(-380px) translateX(25px); opacity: 0; } } @keyframes ring-spin { to { transform: rotate(360deg); } } @keyframes player-lunge { 45% { transform: translateX(26px) scale(1.1); } } @keyframes enemy-lunge { 45% { transform: translateX(-26px) scale(1.1); } } @keyframes unit-hit { 20%, 60% { transform: translateX(-5px); filter: brightness(2) sepia(1) saturate(5); } 40%, 80% { transform: translateX(5px); } } @keyframes damage-float { 0% { opacity: 0; transform: translateY(14px) scale(.6); } 20% { opacity: 1; transform: translateY(0) scale(1.2); } 100% { opacity: 0; transform: translateY(-52px) scale(1); } } @keyframes panel-flash { 50% { transform: translateX(3px); } } @keyframes field-shake { 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } } @keyframes overlay-in { from { opacity: 0; } to { opacity: 1; } }
@media (max-width: 640px) { .battlefield { min-height: 350px; }.unit-zone { top: 24%; gap: 3rem; width: 44%; }.unit-row { gap: .15rem; }.unit-row + .unit-row { margin-left: .9rem; }.enemy-zone .unit-row + .unit-row { margin-right: .9rem; }.field-unit :deep(.character-icon-frame) { transform: scale(.78); }.unit-name { margin-top: -8px; font-size: .52rem; }.atb-ring { transform: scale(.78); transform-origin: top; }.versus span { font-size: 1.7rem; } }
</style>
