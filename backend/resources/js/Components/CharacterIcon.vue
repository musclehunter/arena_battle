<script setup>
const props = defineProps({
    iconKey: { type: String, default: null },
    iconIndex: { type: Number, default: 0 },
    gender: { type: String, default: 'unknown' },
    alt: { type: String, default: '' },
    size: { type: Number, default: 100 },
});

const assetBase = (import.meta.env.VITE_ASSET_BASE_URL ?? '').replace(/\/$/, '');
const iconUrl = props.iconKey
    ? `${assetBase}/characters/icons/400/${props.iconKey}_${props.gender}_${props.iconIndex}_400.png`
    : null;
</script>

<template>
    <div class="character-icon-frame" :style="{ width: size + 'px', height: size + 'px' }">
        <img v-if="iconUrl"
             :src="iconUrl"
             :alt="alt"
             class="character-icon-img" />
        <div v-else class="character-icon-placeholder" />
    </div>
</template>

<style scoped>
.character-icon-frame {
    border: 2px solid #6b7280;
    border-radius: 8px;
    background-color: #374151;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.character-icon-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.character-icon-placeholder {
    width: 100%;
    height: 100%;
    background-color: #1f2937;
}
</style>
