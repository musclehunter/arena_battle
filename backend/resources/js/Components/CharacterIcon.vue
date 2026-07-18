<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    iconKey: { type: String, default: null },
    iconIndex: { type: Number, default: 0 },
    gender: { type: String, default: 'unknown' },
    name: { type: String, default: '' },
    alt: { type: String, default: '' },
    size: { type: Number, default: 100 },
    rounded: { type: Boolean, default: true },
    fallbackLabel: { type: String, default: '' },
});

const srcAttempt = ref(0); // 0: png, 1: svg, 2: inline placeholder

const assetBase = (import.meta.env.VITE_ASSET_BASE_URL ?? '').replace(/\/$/, '');

const normalizedGender = computed(() => {
    const g = String(props.gender || 'unknown').toLowerCase();
    if (['male', 'female', 'unknown'].includes(g)) return g;
    return 'unknown';
});

const iconIndex = computed(() => Math.max(0, Number.parseInt(props.iconIndex, 10) || 0));
const fallbackIconIndex = computed(() => iconIndex.value % 3);

const basePath = computed(() => {
    if (!props.iconKey) return null;
    const index = srcAttempt.value < 2 ? iconIndex.value : fallbackIconIndex.value;
    const filename = `${props.iconKey}_${normalizedGender.value}_${index}_400`;
    if (assetBase) {
        return `${assetBase}/characters/icons/400/${filename}`;
    }
    return `/images/characters/icons/400/${filename}`;
});

const iconUrl = computed(() => {
    if (!basePath.value) return null;
    const ext = srcAttempt.value === 0 || srcAttempt.value === 2 ? 'png' : (srcAttempt.value === 1 || srcAttempt.value === 3 ? 'svg' : null);
    if (!ext) return null;
    return `${basePath.value}.${ext}`;
});

const onImageError = () => {
    if (srcAttempt.value < 4) {
        srcAttempt.value++;
    }
};

const placeholder = computed(() => {
    const label = props.fallbackLabel || props.name || props.alt || '?';
    const words = label.split(/[\s_]+/).filter(Boolean);
    const initials = words
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();

    const colors = [
        ['#1e293b', '#3b82f6'],
        ['#1e293b', '#ef4444'],
        ['#1e293b', '#10b981'],
        ['#1e293b', '#f59e0b'],
        ['#1e293b', '#8b5cf6'],
        ['#1e293b', '#ec4899'],
    ];

    let sum = 0;
    for (const ch of label) sum += ch.charCodeAt(0);
    const [bg, fg] = colors[sum % colors.length];

    const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">
  <defs>
    <radialGradient id="g" cx="50%" cy="50%" r="70%">
      <stop offset="0%" stop-color="${fg}" stop-opacity="0.45"/>
      <stop offset="100%" stop-color="${bg}" stop-opacity="1"/>
    </radialGradient>
  </defs>
  <rect width="400" height="400" fill="${bg}"/>
  <circle cx="200" cy="200" r="150" fill="url(#g)"/>
  <text x="200" y="230" font-family="ui-sans-serif, system-ui, sans-serif" font-size="120" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="middle">${initials}</text>
</svg>`;

    return `data:image/svg+xml;base64,${btoa(unescape(encodeURIComponent(svg)))}`;
});

const borderRadius = computed(() => (props.rounded ? '9999px' : '8px'));
</script>

<template>
    <div
        class="character-icon-frame"
        :style="{ width: size + 'px', height: size + 'px', borderRadius }"
    >
        <img
            v-if="iconUrl"
            :src="iconUrl"
            :alt="alt || name || 'character icon'"
            class="character-icon-img"
            :style="{ borderRadius }"
            @error="onImageError"
        />
        <img
            v-else
            :src="placeholder"
            :alt="alt || name || 'character placeholder'"
            class="character-icon-img"
            :style="{ borderRadius }"
        />
    </div>
</template>

<style scoped>
.character-icon-frame {
    border: 2px solid #4b5563;
    background-color: #1f2937;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.2);
}

.character-icon-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
</style>
